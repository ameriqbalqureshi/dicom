import dicomParser from 'dicom-parser';
import { Decoder as JpegLosslessDecoder } from 'jpeg-lossless-decoder-js';
import { readArchiveEntry } from './dicom-zip';

const IMPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2';
const EXPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2.1';
const EXPLICIT_VR_BIG_ENDIAN = '1.2.840.10008.1.2.2';
const JPEG_LOSSLESS = '1.2.840.10008.1.2.4.57';
const JPEG_LOSSLESS_FIRST_ORDER = '1.2.840.10008.1.2.4.70';

const UNCOMPRESSED_TRANSFER_SYNTAXES = new Set([
    IMPLICIT_VR_LITTLE_ENDIAN,
    EXPLICIT_VR_LITTLE_ENDIAN,
    EXPLICIT_VR_BIG_ENDIAN,
]);

const JPEG_LOSSLESS_TRANSFER_SYNTAXES = new Set([JPEG_LOSSLESS, JPEG_LOSSLESS_FIRST_ORDER]);

const PREFETCH_COUNT = 4;

const DEFAULT_FRAME_RATE = 15;
const MIN_FRAME_RATE = 1;
const MAX_FRAME_RATE = 60;

const LENS_SIZE = 220;
const TOUCH_LENS_GAP = 48;
const LENS_CROSSHAIR_GAP = 6;
const LENS_CROSSHAIR_LENGTH = 12;
const MAGNIFICATIONS = [2, 3, 4, 6, 8];
const DEFAULT_MAGNIFICATION = 3;

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
const CALIPER_COLOR = '#facc15';
const SELECTED_CALIPER_COLOR = '#38bdf8';
const CALIPER_HANDLE_RADIUS = 8;
const CALIPER_TICK_LENGTH = 6;
const MIN_CALIPER_LENGTH = 3;

/**
 * Where to put the top left of the lens in a viewport, for a pointer at the given position within it. A mouse looks
 * through the lens; a finger would hide it, so the lens goes above the finger, or else beside or below it.
 *
 * @returns {{left: number, top: number}}
 */
export function lensPosition({ x, y, isTouch }, viewportWidth, viewportHeight) {
    if (!isTouch) {
        return { left: x - LENS_SIZE / 2, top: y - LENS_SIZE / 2 };
    }

    const reach = LENS_SIZE + TOUCH_LENS_GAP;
    const keepInside = (position, viewportSize) => Math.max(0, Math.min(position, viewportSize - LENS_SIZE));
    const centredLeft = keepInside(x - LENS_SIZE / 2, viewportWidth);
    const centredTop = keepInside(y - LENS_SIZE / 2, viewportHeight);

    if (y >= LENS_SIZE + TOUCH_LENS_GAP / 2) {
        return { left: centredLeft, top: Math.max(0, y - reach) };
    }

    if (x + reach <= viewportWidth) {
        return { left: x + TOUCH_LENS_GAP, top: centredTop };
    }

    if (x >= reach) {
        return { left: x - reach, top: centredTop };
    }

    return { left: centredLeft, top: keepInside(y + TOUCH_LENS_GAP, viewportHeight) };
}

/**
 * Parse the header and element offsets of a DICOM file once, so its frames can be decoded repeatedly.
 */
export function parseFile(byteArray) {
    const dataSet = dicomParser.parseDicom(byteArray);

    return { dataSet, byteArray, transferSyntax: dataSet.string('x00020010')?.trim() };
}

/**
 * The playback rate the modality recorded for a multi-frame image, in frames per second.
 */
function recordedFrameRate(dataSet) {
    const recommended = dataSet.intString('x00082144') ?? dataSet.intString('x00180040');

    if (recommended > 0) {
        return recommended;
    }

    const frameTime = dataSet.floatString('x00181063');

    return frameTime > 0 ? 1000 / frameTime : null;
}

/**
 * Read a two-valued spacing attribute (row spacing, column spacing) in millimetres.
 */
function spacingPair(dataSet, tag) {
    const row = dataSet.floatString(tag, 0);
    const column = dataSet.floatString(tag, 1);

    return row > 0 && column > 0 ? { row, column } : null;
}

/**
 * The physical size of one pixel in millimetres, preferring calibrated Pixel Spacing and falling back to
 * Imager Pixel Spacing corrected by the estimated radiographic magnification (as XA and other projection images record it).
 */
function pixelSpacing(dataSet) {
    const calibrated = spacingPair(dataSet, 'x00280030');

    if (calibrated) {
        return { ...calibrated, source: 'pixel spacing' };
    }

    const imager = spacingPair(dataSet, 'x00181164');

    if (!imager) {
        return null;
    }

    const magnification = dataSet.floatString('x00181114');

    if (magnification > 0) {
        return { row: imager.row / magnification, column: imager.column / magnification, source: 'imager spacing ÷ magnification' };
    }

    return { ...imager, source: 'detector plane, approx.' };
}

/**
 * Return the compressed bytes of one frame of encapsulated pixel data.
 */
function encapsulatedFrame(file, pixelElement, frame, numberOfFrames) {
    if (numberOfFrames === 1) {
        return dicomParser.readEncapsulatedPixelDataFromFragments(file.dataSet, pixelElement, 0, pixelElement.fragments.length);
    }

    file.basicOffsetTable ??= pixelElement.basicOffsetTable.length > 0
        ? pixelElement.basicOffsetTable
        : dicomParser.createJPEGBasicOffsetTable(file.dataSet, pixelElement);

    return dicomParser.readEncapsulatedImageFrame(file.dataSet, pixelElement, frame, file.basicOffsetTable);
}

/**
 * Read one frame of greyscale samples as a typed array, decompressing it when needed.
 */
function readGreyscaleSamples(file, pixelElement, frame, { pixelCount, bitsAllocated, isSigned, numberOfFrames }) {
    const bytesPerSample = bitsAllocated / 8;

    if (JPEG_LOSSLESS_TRANSFER_SYNTAXES.has(file.transferSyntax)) {
        const compressed = encapsulatedFrame(file, pixelElement, frame, numberOfFrames);
        const decoded = new JpegLosslessDecoder().decode(compressed.buffer, compressed.byteOffset, compressed.length, bytesPerSample);
        const SampleArray = bitsAllocated === 8
            ? (isSigned ? Int8Array : Uint8Array)
            : (isSigned ? Int16Array : Uint16Array);

        return new SampleArray(decoded.buffer, decoded.byteOffset, pixelCount);
    }

    const frameBytes = pixelCount * bytesPerSample;
    const frameOffset = pixelElement.dataOffset + frame * frameBytes;

    if (frameOffset + frameBytes > file.byteArray.length) {
        throw new Error('The pixel data is shorter than the image dimensions require.');
    }

    const view = new DataView(file.byteArray.buffer, file.byteArray.byteOffset + frameOffset, frameBytes);
    const littleEndian = file.transferSyntax !== EXPLICIT_VR_BIG_ENDIAN;
    const samples = bitsAllocated === 8
        ? (isSigned ? new Int8Array(pixelCount) : new Uint8Array(pixelCount))
        : (isSigned ? new Int16Array(pixelCount) : new Uint16Array(pixelCount));

    for (let i = 0; i < pixelCount; i++) {
        if (bitsAllocated === 8) {
            samples[i] = isSigned ? view.getInt8(i) : view.getUint8(i);
        } else {
            samples[i] = isSigned ? view.getInt16(i * 2, littleEndian) : view.getUint16(i * 2, littleEndian);
        }
    }

    return samples;
}

/**
 * Decode one frame of a parsed DICOM file into modality values (or RGB for colour images).
 */
export function decodeFrame(file, frame) {
    const { dataSet, transferSyntax } = file;
    const isUncompressed = UNCOMPRESSED_TRANSFER_SYNTAXES.has(transferSyntax);

    if (!isUncompressed && !JPEG_LOSSLESS_TRANSFER_SYNTAXES.has(transferSyntax)) {
        throw new Error(`This image uses a compressed transfer syntax (${transferSyntax}) that the viewer cannot display yet.`);
    }

    const pixelElement = dataSet.elements.x7fe00010;

    if (!pixelElement) {
        throw new Error('This instance has no pixel data.');
    }

    const rows = dataSet.uint16('x00280010');
    const columns = dataSet.uint16('x00280011');
    const samplesPerPixel = dataSet.uint16('x00280002') ?? 1;
    const bitsAllocated = dataSet.uint16('x00280100');
    const isSigned = dataSet.uint16('x00280103') === 1;
    const planarConfiguration = dataSet.uint16('x00280006') ?? 0;
    const photometric = dataSet.string('x00280004')?.trim() ?? 'MONOCHROME2';
    const numberOfFrames = dataSet.intString('x00280008') ?? 1;

    if (!rows || !columns) {
        throw new Error('This instance has no image dimensions.');
    }

    if (![8, 16].includes(bitsAllocated)) {
        throw new Error(`Images with ${bitsAllocated} bits allocated are not supported yet.`);
    }

    const pixelCount = rows * columns;

    if (samplesPerPixel === 3) {
        if (!isUncompressed || bitsAllocated !== 8 || photometric !== 'RGB') {
            throw new Error(`Colour images with photometric interpretation ${photometric} are not supported yet.`);
        }

        const frameBytes = pixelCount * 3;
        const frameOffset = pixelElement.dataOffset + frame * frameBytes;

        if (frameOffset + frameBytes > file.byteArray.length) {
            throw new Error('The pixel data is shorter than the image dimensions require.');
        }

        const view = new DataView(file.byteArray.buffer, file.byteArray.byteOffset + frameOffset, frameBytes);
        const rgb = new Uint8ClampedArray(pixelCount * 3);

        for (let i = 0; i < pixelCount; i++) {
            for (let channel = 0; channel < 3; channel++) {
                rgb[i * 3 + channel] = planarConfiguration === 0
                    ? view.getUint8(i * 3 + channel)
                    : view.getUint8(channel * pixelCount + i);
            }
        }

        return { rows, columns, frameRate: recordedFrameRate(dataSet), pixelSpacing: pixelSpacing(dataSet), isColor: true, rgb };
    }

    const slope = dataSet.floatString('x00281053') ?? 1;
    const intercept = dataSet.floatString('x00281052') ?? 0;
    const samples = readGreyscaleSamples(file, pixelElement, frame, { pixelCount, bitsAllocated, isSigned, numberOfFrames });
    const values = new Float32Array(pixelCount);
    let min = Infinity;
    let max = -Infinity;

    for (let i = 0; i < pixelCount; i++) {
        const value = samples[i] * slope + intercept;
        values[i] = value;
        min = Math.min(min, value);
        max = Math.max(max, value);
    }

    const windowCenter = dataSet.floatString('x00281050');
    const windowWidth = dataSet.floatString('x00281051');

    return {
        rows,
        columns,
        frameRate: recordedFrameRate(dataSet),
        pixelSpacing: pixelSpacing(dataSet),
        isColor: false,
        values,
        isMonochrome1: photometric === 'MONOCHROME1',
        defaultWindowCenter: windowCenter ?? (min + max) / 2,
        defaultWindowWidth: windowWidth > 0 ? windowWidth : Math.max(max - min, 1),
        valueRange: Math.max(max - min, 1),
    };
}

/**
 * Alpine component that renders a series of DICOM images on a canvas with window/level and scrolling.
 *
 * @param {Array<{id: number, label: string, slices: Array<{url: string, frame: number}>}>} series
 */
export default function dicomViewer(series) {
    const fileCache = new Map();
    let image = null;
    let renderQueued = false;
    let loadToken = 0;
    let playSession = 0;
    let dragOrigin = null;
    let pointer = null;
    let onFullscreenChange = null;
    let resizeObserver = null;
    const measurements = new Map();
    const calibrations = new Map();
    let selectedMeasurement = null;
    let caliperDrag = null;

    async function downloadFile(url) {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`The image could not be loaded (HTTP ${response.status}).`);
        }

        return new Uint8Array(await response.arrayBuffer());
    }

    /**
     * Load an image's file, which for an image stored in a zip means just its part of the zip.
     */
    function fetchFile(url, archiveEntry) {
        if (!fileCache.has(url)) {
            const request = (archiveEntry ? readArchiveEntry(url, archiveEntry) : downloadFile(url)).then(parseFile);

            request.catch(() => fileCache.delete(url));
            fileCache.set(url, request);
        }

        return fileCache.get(url);
    }

    return {
        series,
        seriesIndex: 0,
        sliceIndex: 0,
        windowCenter: 0,
        windowWidth: 1,
        invert: false,
        status: 'idle',
        error: null,
        image: null,
        playing: false,
        playPending: false,
        frameRate: DEFAULT_FRAME_RATE,
        isFullscreen: false,
        magnifier: false,
        magnification: DEFAULT_MAGNIFICATION,
        magnifications: MAGNIFICATIONS,
        lensVisible: false,
        caliper: false,
        measurementCount: 0,
        calibrating: false,
        knownLength: '',
        isCalibrated: false,
        spacingLabel: '',

        get currentSeries() {
            return this.series[this.seriesIndex] ?? null;
        },

        get slices() {
            return this.currentSeries?.slices ?? [];
        },

        init() {
            onFullscreenChange = () => {
                this.isFullscreen = document.fullscreenElement === this.$refs.stage;
                this.queueRender();
            };
            document.addEventListener('fullscreenchange', onFullscreenChange);

            resizeObserver = new ResizeObserver(() => this.drawMeasurements());
            resizeObserver.observe(this.$refs.viewport);

            this.loadSlice({ resetWindow: true });
        },

        destroy() {
            this.pause();
            document.removeEventListener('fullscreenchange', onFullscreenChange);
            resizeObserver?.disconnect();
        },

        async selectSeries(index) {
            if (index === this.seriesIndex || index < 0 || index >= this.series.length) {
                return;
            }

            const shouldKeepPlaying = this.playing || this.playPending;

            this.pause();
            this.playPending = shouldKeepPlaying;
            const session = playSession;
            fileCache.clear();
            this.calibrating = false;
            this.seriesIndex = index;
            this.sliceIndex = 0;
            this.$refs.viewport.focus();
            await this.loadSlice({ resetWindow: true });

            if (session === playSession && this.playPending) {
                this.resumePlayback();
            }
        },

        // Starts only after the first image, which sets the series' frame rate and window. A series with one image has
        // nothing to play, so the intent carries on to the next series.
        resumePlayback() {
            if (this.status === 'error') {
                this.playPending = false;
            } else if (this.slices.length >= 2) {
                this.play();
            }
        },

        previousSeries() {
            this.selectSeries(this.seriesIndex - 1);
        },

        nextSeries() {
            this.selectSeries(this.seriesIndex + 1);
        },

        goTo(index) {
            this.pause();

            const next = Math.min(Math.max(index, 0), this.slices.length - 1);

            if (next !== this.sliceIndex) {
                this.sliceIndex = next;
                this.loadSlice();
            }
        },

        step(offset) {
            this.goTo(this.sliceIndex + offset);
        },

        first() {
            this.goTo(0);
        },

        last() {
            this.goTo(this.slices.length - 1);
        },

        scroll(deltaY) {
            this.step(Math.sign(deltaY));
        },

        togglePlay() {
            this.playing || this.playPending ? this.pause() : this.play();
        },

        async play() {
            if (this.playing || this.slices.length < 2) {
                return;
            }

            const session = ++playSession;
            this.playPending = false;
            this.playing = true;

            while (session === playSession) {
                const startedAt = performance.now();
                this.sliceIndex = (this.sliceIndex + 1) % this.slices.length;
                await this.loadSlice();

                const delay = 1000 / this.frameRate - (performance.now() - startedAt);
                await new Promise((resolve) => setTimeout(resolve, Math.max(0, delay)));
            }
        },

        pause() {
            playSession++;
            this.playPending = false;
            this.playing = false;
        },

        setFrameRate(value) {
            this.frameRate = Math.min(Math.max(Math.round(Number(value) || DEFAULT_FRAME_RATE), MIN_FRAME_RATE), MAX_FRAME_RATE);
        },

        async toggleFullscreen() {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await this.$refs.stage.requestFullscreen?.();
            }

            this.$refs.viewport.focus();
        },

        handleKey(event) {
            const tag = event.target.tagName;

            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(tag) || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            if (tag === 'BUTTON' && (event.key === ' ' || event.key === 'Enter')) {
                return;
            }

            const actions = {
                ArrowRight: () => this.step(1),
                ArrowDown: () => this.step(1),
                ArrowLeft: () => this.step(-1),
                ArrowUp: () => this.step(-1),
                Home: () => this.first(),
                End: () => this.last(),
                PageUp: () => this.previousSeries(),
                PageDown: () => this.nextSeries(),
                ' ': () => this.togglePlay(),
                f: () => this.toggleFullscreen(),
                i: () => this.toggleInvert(),
                r: () => this.resetView(),
                m: () => this.toggleMagnifier(),
                c: () => this.toggleCaliper(),
                Delete: () => this.deleteMeasurement(),
                Backspace: () => this.deleteMeasurement(),
                Escape: () => this.cancelCalibration(),
                '+': () => this.stepMagnification(1),
                '=': () => this.stepMagnification(1),
                '-': () => this.stepMagnification(-1),
            };

            const action = actions[event.key.length === 1 ? event.key.toLowerCase() : event.key];

            if (action) {
                event.preventDefault();
                action();
            }
        },

        async loadSlice({ resetWindow = false } = {}) {
            const slice = this.slices[this.sliceIndex];

            if (!slice) {
                return;
            }

            const token = ++loadToken;

            if (!this.playing) {
                this.status = 'loading';
            }

            try {
                const decoded = decodeFrame(await fetchFile(slice.url, this.currentSeries.archiveEntries?.[slice.url]), slice.frame);

                if (token !== loadToken) {
                    return;
                }

                const isFirstImage = image === null;
                image = decoded;
                this.image = { rows: decoded.rows, columns: decoded.columns, isColor: decoded.isColor };

                if ((resetWindow || isFirstImage) && !decoded.isColor) {
                    this.windowCenter = decoded.defaultWindowCenter;
                    this.windowWidth = decoded.defaultWindowWidth;
                }

                if (resetWindow || isFirstImage) {
                    this.setFrameRate(decoded.frameRate ?? DEFAULT_FRAME_RATE);
                }

                this.status = 'ready';
                this.error = null;
                this.queueRender();
                this.prefetch();
            } catch (exception) {
                if (token !== loadToken) {
                    return;
                }

                this.status = 'error';
                this.error = exception.message ?? 'The image could not be displayed.';
                this.clearCanvas();
            }
        },

        prefetch() {
            this.slices
                .slice(this.sliceIndex + 1, this.sliceIndex + 1 + PREFETCH_COUNT)
                .forEach((slice) => fetchFile(slice.url, this.currentSeries.archiveEntries?.[slice.url]).catch(() => {}));
        },

        resetView() {
            this.invert = false;

            if (image && !image.isColor) {
                this.windowCenter = image.defaultWindowCenter;
                this.windowWidth = image.defaultWindowWidth;
            }

            this.queueRender();
        },

        toggleInvert() {
            this.invert = !this.invert;
            this.queueRender();
        },

        startAdjust(event) {
            if (event.button !== 0 || !image) {
                return;
            }

            if (this.caliper) {
                this.startMeasurement(event);

                return;
            }

            if (image.isColor || (this.magnifier && event.pointerType === 'touch')) {
                return;
            }

            this.$refs.viewport.focus();
            this.$refs.viewport.setPointerCapture(event.pointerId);
            dragOrigin = { x: event.clientX, y: event.clientY };
        },

        adjust(event) {
            if (caliperDrag) {
                this.moveMeasurement(event);

                return;
            }

            if (!dragOrigin || !image) {
                return;
            }

            const sensitivity = Math.max(image.valueRange / 512, 0.5);
            this.windowWidth = Math.max(1, this.windowWidth + (event.clientX - dragOrigin.x) * sensitivity);
            this.windowCenter += (event.clientY - dragOrigin.y) * sensitivity;
            dragOrigin = { x: event.clientX, y: event.clientY };
            this.queueRender();
        },

        stopAdjust(event) {
            if ((dragOrigin || caliperDrag) && this.$refs.viewport.hasPointerCapture(event.pointerId)) {
                this.$refs.viewport.releasePointerCapture(event.pointerId);
            }

            if (caliperDrag) {
                this.finishMeasurement();
            }

            dragOrigin = null;
        },

        /**
         * Convert a pointer position to image pixel coordinates, along with the current screen pixels per image pixel.
         */
        imagePointAt(clientX, clientY) {
            const bounds = this.$refs.canvas.getBoundingClientRect();
            const scale = Math.min(bounds.width / image.columns, bounds.height / image.rows);

            return {
                x: (clientX - bounds.left - (bounds.width - image.columns * scale) / 2) / scale,
                y: (clientY - bounds.top - (bounds.height - image.rows * scale) / 2) / scale,
                scale,
            };
        },

        currentMeasurements() {
            const key = `${this.currentSeries?.id}:${this.sliceIndex}`;

            if (!measurements.has(key)) {
                measurements.set(key, []);
            }

            return measurements.get(key);
        },

        toggleCaliper() {
            this.caliper = !this.caliper;

            if (!this.caliper) {
                this.calibrating = false;
            }

            this.drawMeasurements();
        },

        /**
         * Grab the endpoint of an existing caliper under the pointer, or start drawing a new one.
         */
        startMeasurement(event) {
            this.$refs.viewport.focus();

            const point = this.imagePointAt(event.clientX, event.clientY);
            const list = this.currentMeasurements();
            const tolerance = CALIPER_HANDLE_RADIUS / point.scale;
            let grabbed = null;

            for (const measurement of [...list].reverse()) {
                const endpoint = [measurement.end, measurement.start]
                    .find((candidate) => Math.hypot(candidate.x - point.x, candidate.y - point.y) <= tolerance);

                if (endpoint) {
                    grabbed = { measurement, endpoint, isNew: false };
                    break;
                }
            }

            if (!grabbed) {
                if (point.x < 0 || point.y < 0 || point.x > image.columns || point.y > image.rows) {
                    return;
                }

                const measurement = { start: { x: point.x, y: point.y }, end: { x: point.x, y: point.y } };
                list.push(measurement);
                grabbed = { measurement, endpoint: measurement.end, isNew: true };
            }

            caliperDrag = grabbed;
            selectedMeasurement = grabbed.measurement;
            this.$refs.viewport.setPointerCapture(event.pointerId);
            this.drawMeasurements();
        },

        moveMeasurement(event) {
            const point = this.imagePointAt(event.clientX, event.clientY);

            caliperDrag.endpoint.x = Math.min(Math.max(point.x, 0), image.columns);
            caliperDrag.endpoint.y = Math.min(Math.max(point.y, 0), image.rows);
            this.drawMeasurements();
        },

        finishMeasurement() {
            const { measurement, isNew } = caliperDrag;
            caliperDrag = null;

            if (isNew && this.pixelLength(measurement) * this.imagePointAt(0, 0).scale < MIN_CALIPER_LENGTH) {
                this.removeMeasurement(measurement);
            }

            this.drawMeasurements();
        },

        removeMeasurement(measurement) {
            const list = this.currentMeasurements();
            const index = list.indexOf(measurement);

            if (index !== -1) {
                list.splice(index, 1);
            }

            if (selectedMeasurement === measurement) {
                selectedMeasurement = null;
            }
        },

        /**
         * The caliper that delete and calibrate act on: the selected one on this image, otherwise the latest.
         */
        targetMeasurement() {
            const list = this.currentMeasurements();

            return list.includes(selectedMeasurement) ? selectedMeasurement : list.at(-1);
        },

        deleteMeasurement() {
            const target = this.targetMeasurement();

            if (target) {
                this.removeMeasurement(target);
                this.drawMeasurements();
            }
        },

        clearMeasurements() {
            this.currentMeasurements().length = 0;
            selectedMeasurement = null;
            this.drawMeasurements();
        },

        pixelLength(measurement) {
            return Math.hypot(measurement.end.x - measurement.start.x, measurement.end.y - measurement.start.y);
        },

        /**
         * The length of a caliper in millimetres when the image is calibrated, otherwise in pixels.
         *
         * @returns {{value: number, unit: string}}
         */
        measuredLength(measurement) {
            const calibration = calibrations.get(this.currentSeries?.id);

            if (calibration) {
                return { value: this.pixelLength(measurement) * calibration, unit: 'mm' };
            }

            if (image.pixelSpacing) {
                const dx = (measurement.end.x - measurement.start.x) * image.pixelSpacing.column;
                const dy = (measurement.end.y - measurement.start.y) * image.pixelSpacing.row;

                return { value: Math.hypot(dx, dy), unit: 'mm' };
            }

            return { value: this.pixelLength(measurement), unit: 'px' };
        },

        startCalibration() {
            if (!this.targetMeasurement()) {
                return;
            }

            this.knownLength = '';
            this.calibrating = true;
            this.$nextTick(() => this.$refs.knownLength.focus());
        },

        /**
         * Calibrate the series so the target caliper measures the known length the user entered, in millimetres.
         */
        applyCalibration() {
            const knownLength = Number(this.knownLength);
            const reference = this.targetMeasurement();

            if (!(knownLength > 0) || !reference || this.pixelLength(reference) === 0) {
                return;
            }

            calibrations.set(this.currentSeries.id, knownLength / this.pixelLength(reference));
            this.cancelCalibration();
            this.drawMeasurements();
        },

        cancelCalibration() {
            this.calibrating = false;
            this.$refs.viewport.focus();
        },

        clearCalibration() {
            calibrations.delete(this.currentSeries?.id);
            this.drawMeasurements();
        },

        /**
         * Draw the current image's calipers into the SVG overlay, whose viewBox matches the image's pixel grid.
         */
        drawMeasurements() {
            const overlay = this.$refs.overlay;
            overlay.replaceChildren();

            const calibration = calibrations.get(this.currentSeries?.id);
            this.isCalibrated = Boolean(calibration);

            if (!image) {
                this.measurementCount = 0;
                this.spacingLabel = '';

                return;
            }

            const list = this.currentMeasurements();
            this.measurementCount = list.length;

            if (calibration) {
                this.spacingLabel = `Calibrated · ${calibration.toFixed(4)} mm/px`;
            } else if (image.pixelSpacing) {
                this.spacingLabel = `${image.pixelSpacing.column.toFixed(4)} mm/px · ${image.pixelSpacing.source}`;
            } else {
                this.spacingLabel = 'Uncalibrated · pixels';
            }

            const { scale } = this.imagePointAt(0, 0);

            if (!(scale > 0)) {
                return;
            }

            overlay.setAttribute('viewBox', `0 0 ${image.columns} ${image.rows}`);

            const screenPixel = 1 / scale;
            const append = (name, attributes) => {
                const node = document.createElementNS(SVG_NAMESPACE, name);
                Object.entries(attributes).forEach(([attribute, value]) => node.setAttribute(attribute, value));
                overlay.appendChild(node);

                return node;
            };

            for (const measurement of list) {
                const { start, end } = measurement;
                const color = measurement === selectedMeasurement ? SELECTED_CALIPER_COLOR : CALIPER_COLOR;
                const length = this.pixelLength(measurement) || 1;
                const tickX = -(end.y - start.y) / length * CALIPER_TICK_LENGTH * screenPixel;
                const tickY = (end.x - start.x) / length * CALIPER_TICK_LENGTH * screenPixel;
                const stroke = { stroke: color, 'stroke-width': 1.5, 'vector-effect': 'non-scaling-stroke' };

                append('line', { x1: start.x, y1: start.y, x2: end.x, y2: end.y, ...stroke });

                for (const endpoint of [start, end]) {
                    append('line', { x1: endpoint.x - tickX, y1: endpoint.y - tickY, x2: endpoint.x + tickX, y2: endpoint.y + tickY, ...stroke });

                    if (this.caliper) {
                        append('circle', { cx: endpoint.x, cy: endpoint.y, r: 3 * screenPixel, fill: 'none', ...stroke });
                    }
                }

                const { value, unit } = this.measuredLength(measurement);
                const label = append('text', {
                    x: end.x + 10 * screenPixel,
                    y: end.y - 6 * screenPixel,
                    fill: color,
                    stroke: '#000',
                    'stroke-width': 3 * screenPixel,
                    'paint-order': 'stroke',
                    'font-size': 13 * screenPixel,
                    'font-family': 'ui-sans-serif, system-ui, sans-serif',
                });
                label.textContent = `${value.toFixed(unit === 'mm' ? 2 : 1)} ${unit}`;
            }
        },

        toggleMagnifier() {
            this.magnifier = !this.magnifier;

            if (!this.magnifier && pointer?.isTouch) {
                pointer = null;
            }

            this.updateLens();
        },

        setMagnification(value) {
            this.magnification = MAGNIFICATIONS.includes(Number(value)) ? Number(value) : DEFAULT_MAGNIFICATION;
            this.updateLens();
        },

        stepMagnification(offset) {
            const index = MAGNIFICATIONS.indexOf(this.magnification) + offset;

            this.magnifier = true;
            this.setMagnification(MAGNIFICATIONS[Math.min(Math.max(index, 0), MAGNIFICATIONS.length - 1)]);
        },

        /**
         * Remember where the pointer is within the viewport, so a lens left in place stays put if the page scrolls.
         */
        trackPointer(event) {
            if (!event.isPrimary) {
                return;
            }

            const bounds = this.$refs.viewport.getBoundingClientRect();
            pointer = { x: event.clientX - bounds.left, y: event.clientY - bounds.top, isTouch: event.pointerType === 'touch' };
            this.updateLens();
        },

        /**
         * Hide the lens when the mouse leaves. A finger leaves whenever it lifts, and the lens then stays where it is, so
         * the image can be seen without a finger in the way.
         */
        leaveViewport(event) {
            if (event.pointerType === 'touch') {
                return;
            }

            pointer = null;
            this.updateLens();
        },

        /**
         * Position the lens for the pointer and draw the magnified region of the rendered image into it. A lens placed by
         * touch is away from the point it magnifies, so a crosshair marks that point.
         */
        updateLens() {
            if (!this.magnifier || !pointer || !image) {
                this.lensVisible = false;

                return;
            }

            const canvas = this.$refs.canvas;
            const viewportBounds = this.$refs.viewport.getBoundingClientRect();
            const { x: imageX, y: imageY, scale } = this.imagePointAt(viewportBounds.left + pointer.x, viewportBounds.top + pointer.y);

            if (imageX < 0 || imageY < 0 || imageX >= image.columns || imageY >= image.rows) {
                this.lensVisible = false;

                return;
            }

            const lens = this.$refs.lens;
            const pixelRatio = window.devicePixelRatio || 1;
            const lensPixels = Math.round(LENS_SIZE * pixelRatio);

            if (lens.width !== lensPixels) {
                lens.width = lensPixels;
                lens.height = lensPixels;
                lens.style.width = `${LENS_SIZE}px`;
                lens.style.height = `${LENS_SIZE}px`;
            }

            const { left, top } = lensPosition(pointer, viewportBounds.width, viewportBounds.height);
            lens.style.transform = `translate(${left}px, ${top}px)`;

            const sourceSize = LENS_SIZE / (scale * this.magnification);
            const context = lens.getContext('2d');
            context.fillStyle = '#000';
            context.fillRect(0, 0, lensPixels, lensPixels);
            context.imageSmoothingEnabled = false;
            context.drawImage(canvas, imageX - sourceSize / 2, imageY - sourceSize / 2, sourceSize, sourceSize, 0, 0, lensPixels, lensPixels);

            if (pointer.isTouch) {
                const centre = lensPixels / 2;
                const gap = LENS_CROSSHAIR_GAP * pixelRatio;
                const end = gap + LENS_CROSSHAIR_LENGTH * pixelRatio;

                context.strokeStyle = SELECTED_CALIPER_COLOR;
                context.lineWidth = pixelRatio;
                context.beginPath();

                for (const [x, y] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                    context.moveTo(centre + x * gap, centre + y * gap);
                    context.lineTo(centre + x * end, centre + y * end);
                }

                context.stroke();
            }

            this.lensVisible = true;
        },

        queueRender() {
            if (renderQueued) {
                return;
            }

            renderQueued = true;
            requestAnimationFrame(() => {
                renderQueued = false;
                this.render();
            });
        },

        render() {
            if (!image) {
                return;
            }

            const canvas = this.$refs.canvas;
            canvas.width = image.columns;
            canvas.height = image.rows;

            const context = canvas.getContext('2d');
            const output = context.createImageData(image.columns, image.rows);
            const pixels = output.data;
            const pixelCount = image.rows * image.columns;

            if (image.isColor) {
                for (let i = 0; i < pixelCount; i++) {
                    for (let channel = 0; channel < 3; channel++) {
                        const value = image.rgb[i * 3 + channel];
                        pixels[i * 4 + channel] = this.invert ? 255 - value : value;
                    }

                    pixels[i * 4 + 3] = 255;
                }
            } else {
                const lower = this.windowCenter - this.windowWidth / 2;
                const scale = 255 / this.windowWidth;
                const flip = image.isMonochrome1 !== this.invert;

                for (let i = 0; i < pixelCount; i++) {
                    let value = (image.values[i] - lower) * scale;
                    value = value < 0 ? 0 : value > 255 ? 255 : value;

                    if (flip) {
                        value = 255 - value;
                    }

                    pixels[i * 4] = value;
                    pixels[i * 4 + 1] = value;
                    pixels[i * 4 + 2] = value;
                    pixels[i * 4 + 3] = 255;
                }
            }

            context.putImageData(output, 0, 0);
            this.updateLens();
            this.drawMeasurements();
        },

        clearCanvas() {
            image = null;
            this.image = null;
            this.lensVisible = false;
            this.drawMeasurements();

            const canvas = this.$refs.canvas;
            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        },
    };
}
