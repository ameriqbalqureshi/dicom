import './bootstrap';
import dicomViewer from './dicom-viewer';
import { isZip, readZip } from './dicom-zip';

window.dicomZip = { isZip, readZip };

document.addEventListener('alpine:init', () => {
    window.Alpine.data('dicomViewer', dicomViewer);
});
