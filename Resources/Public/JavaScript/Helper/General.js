define([], function() {
    function isUsable(element) {
        return element !== null && element !== undefined;
    }
    function sanitizeFileName(fileName) {
        fileName = fileName.toLowerCase();

        const tempArr = fileName.split(".");
        let fileEnding = '';
        if (tempArr.length > 1) {
            fileEnding = '.' + tempArr[tempArr.length - 1];
            fileName = fileName.replace(fileEnding, '');
        }

        fileName = fileName.replace(/_/g, '-');
        fileName = fileName.replace(/ /g, '-');
        fileName = fileName.replace(/\+/g, '-');
        fileName = fileName.replace(/,/g, '-');
        fileName = fileName.replace(/\(/g, '');
        fileName = fileName.replace(/\)/g, '');
        fileName = fileName.replace(/\./g, '-');
        fileName = fileName.replace(/Ä/g, 'ae');
        fileName = fileName.replace(/Ü/g, 'ue');
        fileName = fileName.replace(/Ö/g, 'oe');
        fileName = fileName.replace(/ä/g, 'ae');
        fileName = fileName.replace(/ü/g, 'ue');
        fileName = fileName.replace(/ö/g, 'oe');
        fileName = fileName.replace(/ß/g, 'ss');

        fileName = fileName.replace(/[^a-zA-Z0-9-_\.]/g, '');
        fileName = fileName.replace(/\.{2,}/g, '.');
        fileName = fileName.replace(/_+/g, '_');
        fileName = fileName.replace(/-+/g, '-');

        if (fileName.startsWith('-')) {
            fileName = fileName.substring(1);
        }
        if (fileName.endsWith('-')) {
            fileName = fileName.slice(0, -1);
        }

        fileName = fileName.replace(/_+/g, '_');
        fileName = fileName.replace(/-+/g, '-');
        fileName += fileEnding;

        return fileName;
    }
    return {
        isUsable: isUsable,
        sanitizeFileName: sanitizeFileName
    };
});
