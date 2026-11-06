import dicomParser from 'dicom-parser';

/**
 * Works with zips of DICOM files without unpacking them to disk. On upload the browser reads each image's header and
 * location from the zip, so only the zip itself is sent. The viewer later fetches and inflates one image at a time.
 */

const ZIP_SIGNATURE = 'PK\u0003\u0004';
const LOCAL_HEADER_SIZE = 30;
const STORED = 0;
const DEFLATED = 8;

/** How much of an image is read for its header at first. Headers are usually a few kilobytes. */
const HEADER_BYTES = 1024 * 1024;
const LONGEST_ATTRIBUTE = 1024;

/** How long a signed link to a zip is reused. Links stay valid for 10 minutes; a failed one is simply replaced. */
const SIGNED_LINK_REUSE_MS = 5 * 60 * 1000;

const TEXT_ATTRIBUTES = {
    x00020010: 'transferSyntaxUid',
    x00080016: 'sopClassUid',
    x00080018: 'sopInstanceUid',
    x00080020: 'studyDate',
    x00080050: 'accessionNumber',
    x00080060: 'modality',
    x00081030: 'studyDescription',
    x0008103e: 'seriesDescription',
    x00100010: 'patientName',
    x00100020: 'patientId',
    x00100030: 'patientBirthDate',
    x00100040: 'patientSex',
    x0020000d: 'studyInstanceUid',
    x0020000e: 'seriesInstanceUid',
    x00200011: 'seriesNumber',
    x00200013: 'instanceNumber',
    x00280008: 'numberOfFrames',
};

const signedLinks = new Map();

/** Files in a zip that are never images: the disc's index, and metadata that macOS adds when zipping. */
function isNeverAnImage(path) {
    const name = path.split('/').pop();

    return name.toUpperCase() === 'DICOMDIR' || name.startsWith('._') || name === '.DS_Store' || path.includes('__MACOSX/');
}

export async function isZip(file) {
    return (await file.slice(0, 4).text()) === ZIP_SIGNATURE;
}

/**
 * Inflate deflated data, stopping at `limit` bytes: a header needs only the start of an image, and an entry never gets
 * to produce more than it declared, whatever its compressed data would expand to.
 */
async function inflate(compressed, limit) {
    const reader = compressed.stream().pipeThrough(new DecompressionStream('deflate-raw')).getReader();
    const output = new Uint8Array(limit);
    let filled = 0;

    while (filled < limit) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        const count = Math.min(value.length, limit - filled);
        output.set(value.subarray(0, count), filled);
        filled += count;
    }

    reader.cancel().catch(() => {});

    return output.subarray(0, filled);
}

/** The first `limit` bytes of an entry whose data is at the given place in a file. */
async function readStart(file, { offset, length, method }, limit) {
    const data = file.slice(offset, offset + length);

    return method === STORED ? new Uint8Array(await data.slice(0, limit).arrayBuffer()) : inflate(data, limit);
}

/**
 * Where an entry's data starts and how it is compressed, from its local header. Only that header is authoritative for
 * the start of the data, as its extra field may differ from the one in the zip's directory.
 */
async function locateEntry(file, entry) {
    const header = new DataView(await file.slice(entry.offset, entry.offset + LOCAL_HEADER_SIZE).arrayBuffer());

    if (header.byteLength < LOCAL_HEADER_SIZE || header.getUint32(0, true) !== 0x04034b50) {
        throw new Error('A zip entry could not be found.');
    }

    return {
        offset: entry.offset + LOCAL_HEADER_SIZE + header.getUint16(26, true) + header.getUint16(28, true),
        length: entry.compressedSize,
        size: entry.uncompressedSize,
        method: header.getUint16(8, true),
    };
}

/** Text as the server reads it: UTF-8 where it is valid, otherwise Latin-1. */
function decodeText(bytes) {
    let text;

    try {
        text = new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    } catch {
        text = new TextDecoder('latin1').decode(bytes);
    }

    return text.replace(/^[ \0]+|[ \0]+$/g, '');
}

/** The attributes the server stores, parsing no further than the pixel data. */
function readAttributes(bytes) {
    const dataSet = dicomParser.parseDicom(bytes, { untilTag: 'x7fe00010' });
    const attributes = {};

    for (const [tag, name] of Object.entries(TEXT_ATTRIBUTES)) {
        const element = dataSet.elements[tag];

        if (element && element.length <= LONGEST_ATTRIBUTE) {
            attributes[name] = decodeText(bytes.subarray(element.dataOffset, element.dataOffset + element.length));
        }
    }

    attributes.rows = dataSet.uint16('x00280010');
    attributes.columns = dataSet.uint16('x00280011');

    return attributes;
}

/** An entry's attributes, or null when it is not DICOM. Its whole data is read only if the header is unusually long. */
async function readEntryAttributes(file, location) {
    const start = await readStart(file, location, 132);

    if (start.length < 132 || decodeText(start.subarray(128, 132)) !== 'DICM') {
        return null;
    }

    const firstPart = Math.min(HEADER_BYTES, location.size);

    try {
        return readAttributes(await readStart(file, location, firstPart));
    } catch (error) {
        if (firstPart === location.size) {
            throw error;
        }

        return readAttributes(await readStart(file, location, location.size));
    }
}

/**
 * Find the DICOM images in a zip, reading only their headers.
 *
 * @param {(done: number, total: number) => void} onProgress
 * @returns {Promise<{isEncrypted: boolean, ignored: number, unreadable: string[], images: Array<{name: string, attributes: object, offset: number, length: number, size: number, method: number}>}>}
 */
export async function scanZip(file, onProgress = () => {}) {
    const { BlobReader, ZipReader, configure } = await import('@zip.js/zip.js');

    configure({ useWebWorkers: false });

    const reader = new ZipReader(new BlobReader(file));
    const entries = (await reader.getEntries()).filter((entry) => !entry.directory);
    const candidates = entries.filter((entry) => !isNeverAnImage(entry.filename));
    const scan = { isEncrypted: candidates.some((entry) => entry.encrypted), ignored: entries.length - candidates.length, unreadable: [], images: [] };

    if (!scan.isEncrypted) {
        for (const [index, entry] of candidates.entries()) {
            const name = entry.filename.split('/').pop();
            const location = await locateEntry(file, entry);

            if (![STORED, DEFLATED].includes(location.method)) {
                throw new Error('The zip uses a compression method that cannot be read.');
            }

            try {
                const attributes = await readEntryAttributes(file, location);

                if (attributes) {
                    scan.images.push({ name, attributes, ...location });
                } else {
                    scan.ignored++;
                }
            } catch {
                scan.unreadable.push(name);
            }

            onProgress(index + 1, candidates.length);
        }
    }

    await reader.close();

    return scan;
}

/** Request part of a zip, reusing the signed storage link the application last redirected to for this zip. */
async function fetchRange(url, entry) {
    const headers = { Range: `bytes=${entry.offset}-${entry.offset + entry.length - 1}` };
    const link = signedLinks.get(entry.archive);

    if (link && Date.now() - link.createdAt < SIGNED_LINK_REUSE_MS) {
        try {
            const response = await fetch(link.url, { headers, credentials: 'omit', referrerPolicy: 'no-referrer' });

            if (response.ok) {
                return response;
            }
        } catch {
            // The application is asked for a new link below.
        }

        signedLinks.delete(entry.archive);
    }

    const response = await fetch(url, { headers, credentials: 'same-origin' });

    if (response.redirected) {
        signedLinks.set(entry.archive, { url: response.url, createdAt: Date.now() });
    }

    return response;
}

/**
 * Fetch one image out of a stored zip and inflate it, in memory.
 *
 * @param {string} url The application URL that leads to the zip.
 * @param {{archive: number, offset: number, length: number, size: number, method: number}} entry
 * @returns {Promise<Uint8Array>}
 */
export async function readArchiveEntry(url, entry) {
    const response = await fetchRange(url, entry);

    if (!response.ok) {
        throw new Error(`The image could not be loaded (HTTP ${response.status}).`);
    }

    const received = new Uint8Array(await response.arrayBuffer());
    const data = response.status === 206 ? received : received.subarray(entry.offset, entry.offset + entry.length);

    return entry.method === STORED ? data : inflate(new Blob([data]), entry.size);
}
