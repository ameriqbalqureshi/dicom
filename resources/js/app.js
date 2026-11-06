import './bootstrap';
import dicomViewer from './dicom-viewer';
import { isZip, scanZip } from './dicom-zip';

window.dicomZip = { isZip, scanZip };

document.addEventListener('alpine:init', () => {
    window.Alpine.data('dicomViewer', dicomViewer);
});
