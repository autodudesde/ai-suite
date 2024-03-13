/*
let checkUnload = true;
window.onbeforeunload = function() {
    if(checkUnload) {
        return 'You have unsaved changes!';
    }
};
window.addEventListener('load', function() {
    let forms = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form'));
    forms.forEach(function(form) {
        form.addEventListener('submit', function() {
            checkUnload = false;
        });
    });
    let pageStructureSubmitButton = document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create span.submit-page-structure');
    if(pageStructureSubmitButton !== null) {
        pageStructureSubmitButton.addEventListener('click', function() {
            checkUnload = false;
        });
    }
});
*/
