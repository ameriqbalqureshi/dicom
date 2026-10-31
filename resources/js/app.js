import './bootstrap';
import dicomViewer from './dicom-viewer';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('dicomViewer', dicomViewer);
});
