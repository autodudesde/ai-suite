class Sortable {
    /**
     *
     * @param {object[]} list
     * @param findItemsInSortableFn
     * @returns {object[]}
     */
    findItemsInSortable(list, findItemsInSortableFn = this.findItemsInSortable) {
        let fiisFn = findItemsInSortableFn;
        let items = [];
        list.forEach(function (item, index, arr) {
            let title = item.querySelector('span.title').dataset.title;
            let children = Array.from(item.querySelectorAll(':scope > .list-group.nested-sortable > .list-group-item'));
            let newElement = {};
            if(children.length > 0) {
                newElement = {
                    title: title,
                    children: findItemsInSortableFn(children, fiisFn)
                };
            } else {
                newElement = {
                    title: title
                };
            }
            if(title !== undefined && title !== null && title !== '') {
                items.push(newElement);
            }
        });
        return items;
    }
}

export default new Sortable();
