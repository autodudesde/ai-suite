var nestedSortables = [].slice.call(document.querySelectorAll('.nested-sortable'));
let dialog = document.querySelector('.sortable-wrap .sortable-input');

// Loop through each nested sortable element
for (var i = 0; i < nestedSortables.length; i++) {
    new Sortable(nestedSortables[i], {
        group: 'nested',
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65
    });
}

// add buttons
var addButtonList = Array.from(document.querySelectorAll('.sortable-wrap button.add'));
if (Array.isArray(addButtonList)){
    addButtonList.forEach(function(item, index, arr){
        addButtonDialogEvent(item);
    });
}

// edit buttons
var editButtonList = Array.from(document.querySelectorAll('.sortable-wrap button.edit'));
if (Array.isArray(editButtonList)){
    editButtonList.forEach(function(item, index, arr){
        addButtonDialogEvent(item);
    });
}

// dialog submit button
let dialogSubmitButton = document.querySelector('.sortable-wrap .sortable-input button.submit');
if (dialogSubmitButton !== null && dialogSubmitButton !== undefined){
    dialogSubmitButton.addEventListener('click', function(e){
        e.preventDefault();
        let input = document.querySelector('.sortable-wrap .sortable-input input[type="text"]');
        insertOrUpdateSortableItem(input.value);
        input.value('');
        dialog.classList.remove('active');
    });
}

// dynamic function to insert a sortable item or update the title
function insertOrUpdateSortableItem (title)
{
    let clicked = document.querySelector('.sortable-wrap button.clicked');

    // check if clicked has class 'edit'
    if (clicked.classList.contains('edit')){
        let titleSpan = clicked.previousElementSibling;
        titleSpan.innerHTML = title;
        titleSpan.dataset.title = title;
    } else {
        let newElement = document.createElement('div');
        newElement.classList.add('list-group-item');
        newElement.classList.add('nested');
        let handle = document.createElement('span');
        handle.classList.add('handle');
        handle.innerHTML = document.querySelector('.sortable-wrap div.handle').innerHTML;
        newElement.appendChild(handle);
        let titleSpan = document.createElement('span');
        titleSpan.classList.add('title');
        titleSpan.innerHTML = title;
        titleSpan.dataset.title = title;
        newElement.appendChild(titleSpan);
        let editButton = document.createElement('button');
        editButton.classList.add('edit');
        editButton.classList.add('btn');
        editButton.classList.add('btn-default');
        editButton.innerHTML = document.querySelector('.sortable-wrap button.edit').innerHTML;
        addButtonDialogEvent(editButton);
        newElement.appendChild(editButton);
        let deleteButton = document.createElement('button');
        deleteButton.classList.add('delete');
        deleteButton.classList.add('btn');
        deleteButton.classList.add('btn-default');
        deleteButton.innerHTML = document.querySelector('.sortable-wrap button.delete').innerHTML;
        addButtonDeleteEvent(deleteButton);
        newElement.appendChild(deleteButton);
        let listGroup = document.createElement('div');
        listGroup.classList.add('list-group');
        listGroup.classList.add('nested-sortable');
        newElement.appendChild(listGroup);
        let addButton = document.createElement('button');
        addButton.classList.add('add');
        addButton.innerHTML = clicked.innerHTML;
        addButtonDialogEvent(addButton);
        newElement.appendChild(addButton);

        new Sortable(listGroup, {
            group: 'nested',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65
        });
        clicked.previousElementSibling.appendChild(newElement);
    }
    clicked.classList.remove('clicked');
}

// reduce duplicate code: add event to button
function addButtonDialogEvent (button)
{
    button.addEventListener('click', function(e){
        e.preventDefault();
        dialog.classList.add('active');
        this.classList.add('clicked');
    });
}

function addButtonEditEvent (button)
{
    button.addEventListener('click', function(e){
        e.preventDefault();
        dialog.classList.add('active');
        this.classList.add('clicked');
    });
}

function addButtonDeleteEvent (button)
{
    button.addEventListener('click', function(e){
        e.preventDefault();
        this.parentElement.remove();
    });
}

// hide / show buttons
let toggleButtonVisible = document.querySelector('.toggleButtonVisible');
if (toggleButtonVisible !== null && toggleButtonVisible !== undefined){
    toggleButtonVisible.addEventListener('click', function(e){
        e.preventDefault();
        var listOfAllBUttons = Array.from(document.querySelectorAll('.sortable-wrap button'));
        if (Array.isArray(listOfAllBUttons)){
            listOfAllBUttons.forEach(function(item, index, arr){
                item.classList.toggle('d-none');
            });
        }
    });
}

// delete buttons
var deleteButtonList = Array.from(document.querySelectorAll('.sortable-wrap button.delete'));
if (Array.isArray(deleteButtonList)){
    deleteButtonList.forEach(function(item, index, arr){
        addButtonDeleteEvent(item);
    });
}

// cancel dialog for new item / edit title
let closeDialog = document.querySelector('.sortable-wrap .sortable-input .inner-wrap span.close');
if (toggleButtonVisible !== null && toggleButtonVisible !== undefined){
    closeDialog.addEventListener('click', function(e){
        e.preventDefault();
        dialog.classList.remove('active');
    });
}
