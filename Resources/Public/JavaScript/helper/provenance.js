import Ajax from '@autodudes/ai-suite/helper/ajax.js';

class Provenance {
    recordAssisted(table, uid, field, feature, model) {
        if (!table || !/^\d+$/.test(String(uid))) {
            return;
        }

        Ajax.sendAjaxRequest('aisuite_provenance_assisted', {
            table: table,
            uid: uid,
            field: field || '',
            feature: feature || '',
            model: model || '',
        });
    }

    recordAssistedForFieldName(fieldName, feature, model) {
        const match = /^data\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]/.exec(fieldName || '');
        if (match === null) {
            return;
        }

        this.recordAssisted(match[1], match[2], match[3], feature, model);
    }
}

export default new Provenance();
