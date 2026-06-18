class General {
    isUsable(element) {
        return element !== null && element !== undefined;
    }

    sanitizeFileName(fileName) {
        // In Kleinbuchstaben umwandeln
        fileName = fileName.toLowerCase();

        // Dateiendung finden und extrahieren
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

        // Replace all weird characters
        fileName = fileName.replace(/[^a-zA-Z0-9-_\.]/g, '');
        // Replace multiple dots with a single dot
        fileName = fileName.replace(/\.{2,}/g, '.');
        // Replace multiple underscores with a single underscore
        fileName = fileName.replace(/_+/g, '_');
        // Replace multiple dashes with a single dash
        fileName = fileName.replace(/-+/g, '-');

        // Remove leading dash
        if (fileName.startsWith('-')) {
            fileName = fileName.substring(1);
        }

        // Remove trailing dash
        if (fileName.endsWith('-')) {
            fileName = fileName.slice(0, -1);
        }

        // Replace multiple underscores with a single underscore
        fileName = fileName.replace(/_+/g, '_');
        // Replace multiple dashes with a single dash
        fileName = fileName.replace(/-+/g, '-');

        // Dateiendung wieder hinzufügen
        fileName += fileEnding;

        return fileName;
    }
}

export default new General();
