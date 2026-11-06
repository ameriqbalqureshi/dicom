/**
 * Reads DICOM uploads out of a zip in the browser, so the server only ever receives individual files.
 * Entries are extracted one at a time as they are uploaded, which keeps a large zip out of memory.
 */

const ZIP_SIGNATURE = 'PK\u0003\u0004';

/** Files in a zip that are never images: the disc's index, and metadata that macOS adds when zipping. */
function isNeverAnImage(path) {
    const name = path.split('/').pop();

    return name.toUpperCase() === 'DICOMDIR' || name.startsWith('._') || name === '.DS_Store' || path.includes('__MACOSX/');
}

export async function isZip(file) {
    return (await file.slice(0, 4).text()) === ZIP_SIGNATURE;
}

/**
 * List what a zip holds, without extracting anything yet.
 *
 * @returns {Promise<{isEncrypted: boolean, ignored: number, uploads: Array<{name: string, size: number, read: () => Promise<File>}>}>}
 */
export async function readZip(file) {
    const { BlobReader, BlobWriter, ZipReader, configure } = await import('@zip.js/zip.js');

    configure({ useWebWorkers: false });

    const entries = (await new ZipReader(new BlobReader(file)).getEntries()).filter((entry) => !entry.directory);
    const images = entries.filter((entry) => !isNeverAnImage(entry.filename));

    return {
        isEncrypted: images.some((entry) => entry.encrypted),
        ignored: entries.length - images.length,
        uploads: images.map((entry) => {
            const name = entry.filename.split('/').pop();

            return {
                name,
                size: entry.uncompressedSize,
                read: async () => new File([await entry.getData(new BlobWriter())], name),
            };
        }),
    };
}
