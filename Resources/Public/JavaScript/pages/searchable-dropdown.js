const searchableInputList = Array.from(document.querySelectorAll('.searchable.dropdown ul li input.searchableInput'));
if (Array.isArray(searchableInputList)){
    searchableInputList.forEach(function(item, index, arr){
        item.addEventListener('keyup', function(){
            let dropdownElement = this.parentNode.parentNode.parentNode;
            let filter = this.value.toLowerCase();
            let li = dropdownElement.getElementsByTagName('li');
            let txtValue;
            for (let i = 0; i < li.length; i++) {
                txtValue = li[i].textContent || li[i].innerText;
                if (li[i].getElementsByTagName('a')[0] !== undefined && li[i].getElementsByTagName('a')[0] !== null) {
                    txtValue += ' ' + li[i].getElementsByTagName('a')[0].dataset.value;
                }
                if (txtValue.toLowerCase().indexOf(filter) > -1 || li[i].classList.contains('searchbox')) {
                    li[i].style.display = "";
                } else {
                    li[i].style.display = "none";
                }
            }
        });
        // look for prefill
        let dropdownElement = item.parentNode.parentNode.parentNode;
        let inputProperty = dropdownElement.querySelector('input.searchableInputProperty');
        if (inputProperty.value !== ''){
            let a = dropdownElement.querySelector('li a[data-value="'+inputProperty.value+'"]');
            let dropdownButton = dropdownElement.querySelector('button.dropdown-toggle');
            if (a !== null){
                item.value = a.textContent || a.innerText;
                dropdownButton.textContent = dropdownButton.dataset.initialText + ' ' + item.value;
            }
        }
    });
}

const searchableOptionList = Array.from(document.querySelectorAll('.searchable.dropdown ul li a'));
if (Array.isArray(searchableOptionList)){
    searchableOptionList.forEach(function(item, index, arr){
        item.addEventListener('click', function(e){
            e.preventDefault();
            let dropdownElement = this.parentNode.parentNode.parentNode;
            let txtValue = this.textContent || this.innerText;
            dropdownElement.querySelector('input.searchableInputProperty').value = this.dataset.value;
            dropdownElement.querySelector('input.searchableInput').value = txtValue;
            let dropdownButton = dropdownElement.querySelector('button.dropdown-toggle');
            dropdownButton.textContent = dropdownButton.dataset.initialText + ' ' + txtValue;
        });
    });
}
