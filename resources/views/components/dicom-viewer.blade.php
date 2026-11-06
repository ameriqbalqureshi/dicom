@props(['series'])

<div wire:ignore x-data="dicomViewer(@js($series))" class="grid gap-4 lg:grid-cols-[12rem_1fr]">
    <ul class="flex flex-col gap-2" aria-label="Series">
        <template x-for="(item, index) in series" :key="item.id">
            <li>
                <button type="button" @click="selectSeries(index)"
                    :class="index === seriesIndex ? 'border-sky-600 bg-sky-950/40' : 'border-slate-800 hover:border-slate-600'"
                    class="w-full rounded-lg border px-3 py-2 text-left text-sm">
                    <span class="block font-medium" x-text="item.label"></span>
                    <span class="text-xs text-slate-400" x-text="item.slices.length + (item.isRun ? ' frames' : (item.slices.length === 1 ? ' image' : ' images'))"></span>
                </button>
            </li>
        </template>
    </ul>

    <div x-ref="stage" @keydown="handleKey($event)"
        :class="isFullscreen ? 'bg-slate-950 p-3' : ''"
        class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1" role="group" aria-label="Frame navigation">
                <x-viewer-button label="First image (Home)" x-on:click="first()" x-bind:disabled="sliceIndex === 0">|&lsaquo;</x-viewer-button>
                <x-viewer-button label="Previous image (←)" x-on:click="step(-1)" x-bind:disabled="sliceIndex === 0">&lsaquo;</x-viewer-button>
                <x-viewer-button label="Play / pause (Space)" x-on:click="togglePlay()" x-bind:disabled="slices.length < 2 && !playPending"
                    x-bind:class="(playing || playPending) && 'border-sky-600 bg-sky-950/60'" class="w-20">
                    <span x-text="playing || playPending ? 'Pause' : 'Play'"></span>
                </x-viewer-button>
                <x-viewer-button label="Next image (→)" x-on:click="step(1)" x-bind:disabled="sliceIndex >= slices.length - 1">&rsaquo;</x-viewer-button>
                <x-viewer-button label="Last image (End)" x-on:click="last()" x-bind:disabled="sliceIndex >= slices.length - 1">&rsaquo;|</x-viewer-button>
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-400" x-show="slices.length > 1">
                <input type="range" min="1" max="60" :value="frameRate" @input="setFrameRate($event.target.value)"
                    aria-label="Playback speed in frames per second" class="w-24 accent-sky-500">
                <span class="w-12 tabular-nums text-slate-300" x-text="`${frameRate} fps`"></span>
            </label>

            <div class="flex items-center gap-1" role="group" aria-label="Series navigation">
                <x-viewer-button label="Previous series (Page Up)" x-on:click="previousSeries()" x-bind:disabled="seriesIndex === 0">&laquo; Series</x-viewer-button>
                <x-viewer-button label="Next series (Page Down)" x-on:click="nextSeries()" x-bind:disabled="seriesIndex >= series.length - 1">Series &raquo;</x-viewer-button>
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-1">
                <div class="flex items-center gap-1" role="group" aria-label="Caliper">
                    <x-viewer-button label="Caliper (C)" x-on:click="toggleCaliper()" x-bind:class="caliper && 'border-sky-600 bg-sky-950/60'">Caliper</x-viewer-button>
                    <template x-if="caliper && !calibrating">
                        <div class="flex items-center gap-1">
                            <x-viewer-button label="Calibrate against a known length using the selected caliper" x-on:click="startCalibration()" x-bind:disabled="measurementCount === 0">Calibrate</x-viewer-button>
                            <x-viewer-button label="Remove calibration for this series" x-show="isCalibrated" x-on:click="clearCalibration()">Uncalibrate</x-viewer-button>
                            <x-viewer-button label="Delete selected caliper (Delete)" x-on:click="deleteMeasurement()" x-bind:disabled="measurementCount === 0">Delete</x-viewer-button>
                            <x-viewer-button label="Clear all calipers on this image" x-on:click="clearMeasurements()" x-bind:disabled="measurementCount === 0">Clear</x-viewer-button>
                        </div>
                    </template>
                    <form x-show="calibrating" @submit.prevent="applyCalibration()" class="flex items-center gap-1">
                        <input x-ref="knownLength" type="number" min="0" step="any" x-model="knownLength" @keydown.escape.stop="cancelCalibration()"
                            placeholder="Known length" aria-label="Known length of the selected caliper in millimetres"
                            class="h-8 w-32 rounded-md border border-slate-700 bg-slate-900 px-2 text-sm text-slate-200">
                        <span class="text-xs text-slate-400">mm</span>
                        <button type="submit" class="h-8 rounded-md border border-sky-600 bg-sky-950/60 px-2 text-sm hover:bg-sky-900">Set</button>
                        <x-viewer-button label="Cancel calibration (Esc)" x-on:click="cancelCalibration()">Cancel</x-viewer-button>
                    </form>
                </div>
                <div class="flex items-center gap-1" role="group" aria-label="Magnifier">
                    <x-viewer-button label="Magnifier (M)" x-on:click="toggleMagnifier()" x-bind:class="magnifier && 'border-sky-600 bg-sky-950/60'">Magnify</x-viewer-button>
                    <select x-show="magnifier" :value="magnification" @change="setMagnification($event.target.value)" aria-label="Magnification"
                        class="h-8 rounded-md border border-slate-700 bg-slate-900 px-2 text-sm text-slate-200">
                        <template x-for="option in magnifications" :key="option">
                            <option :value="option" :selected="option === magnification" x-text="`${option}×`"></option>
                        </template>
                    </select>
                </div>
                <x-viewer-button label="Invert (I)" x-on:click="toggleInvert()" x-bind:class="invert && 'bg-slate-800'">Invert</x-viewer-button>
                <x-viewer-button label="Reset window/level (R)" x-on:click="resetView()">Reset</x-viewer-button>
                <x-viewer-button label="Full screen (F)" x-on:click="toggleFullscreen()">
                    <span x-text="isFullscreen ? 'Exit full screen' : 'Full screen'"></span>
                </x-viewer-button>
            </div>
        </div>

        <div x-ref="viewport" tabindex="0"
            @wheel.prevent="scroll($event.deltaY)"
            @pointerdown="trackPointer($event); startAdjust($event)" @pointermove="adjust($event); trackPointer($event)" @pointerleave="leaveViewport($event)" @pointerup="stopAdjust($event)" @pointercancel="stopAdjust($event)"
            @dblclick="toggleFullscreen()"
            :class="isFullscreen ? 'min-h-0 flex-1' : 'h-[70vh]'"
            class="relative flex cursor-crosshair touch-none select-none items-center justify-center overflow-hidden rounded-xl border border-slate-800 bg-black focus:border-sky-700 focus:outline-none">
            <canvas x-ref="canvas" class="h-full w-full object-contain"></canvas>

            <svg x-ref="overlay" preserveAspectRatio="xMidYMid meet" aria-hidden="true"
                class="pointer-events-none absolute inset-0 h-full w-full overflow-visible"></svg>

            <canvas x-ref="lens" x-show="lensVisible"
                class="pointer-events-none absolute left-0 top-0 rounded-full border-2 border-sky-500 bg-black shadow-2xl shadow-black"></canvas>

            <div class="pointer-events-none absolute left-3 top-3 text-xs leading-5 text-slate-300">
                <div x-text="currentSeries?.label"></div>
                <div x-show="slices.length" x-text="`${currentSeries?.isRun ? 'Frame' : 'Image'} ${sliceIndex + 1} / ${slices.length}`"></div>
            </div>

            <div x-show="image" class="pointer-events-none absolute bottom-3 left-3 text-xs leading-5 text-slate-300">
                <div x-text="image ? `${image.columns} × ${image.rows}` : ''"></div>
                <div x-show="image && !image.isColor" x-text="`W ${Math.round(windowWidth)} / L ${Math.round(windowCenter)}`"></div>
                <div x-show="caliper" x-text="spacingLabel"></div>
            </div>

            <div x-show="status === 'loading'" class="absolute right-3 top-3 text-xs text-slate-400">Loading…</div>

            <div x-show="status === 'error'" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-red-300" x-text="error"></div>
        </div>

        <input type="range" min="0" :max="Math.max(slices.length - 1, 0)" :value="sliceIndex" @input="goTo(Number($event.target.value))"
            x-show="slices.length > 1" aria-label="Image" class="w-full accent-sky-500">

        <p class="text-xs text-slate-500">
            Drag: window/level · Scroll or ←/→: change image · Space: play/pause · Home/End: first/last ·
            Page Up/Down: series · F or double-click: full screen · I: invert · R: reset ·
            M: magnifier · +/−: magnification · C: caliper (drag to measure, drag an end to adjust, Delete: remove)
        </p>
        <p x-show="magnifier" class="text-xs text-slate-500">
            On a touch screen, touch or drag on the image to place the magnifier. It stays when you lift your finger; press Magnify to close it.
        </p>
    </div>
</div>
