import './bootstrap';
import '../../vendor/wire-elements/modal/resources/js/modal';

document.addEventListener("DOMContentLoaded", setupScreenWidthButton);
function setupScreenWidthButton(){
    let toggleScreenWidthButton = document.getElementById('toggleScreenWidthButton');
    let toggleScreenWidthContent = document.getElementById('toggleScreenWidthContent');
    if(toggleScreenWidthContent && toggleScreenWidthContent.classList.contains('max-w-7xl') && localStorage.getItem('fullWidth') === 'true'){
        toggleScreenWidth();
    }

    if(toggleScreenWidthButton)
    {
        toggleScreenWidthButton.addEventListener('click', toggleScreenWidth);
    }
}

function toggleScreenWidth(){
    let toggleScreenWidthContent = document.getElementById('toggleScreenWidthContent');
    let screenArrowsPointingIn = document.getElementById('screenArrowsPointingIn');
    let screenArrowsPointingOut = document.getElementById('screenArrowsPointingOut');
    screenArrowsPointingIn.classList.toggle('hidden');
    screenArrowsPointingOut.classList.toggle('hidden');
    toggleScreenWidthContent.classList.toggle('max-w-7xl');
    localStorage.setItem('fullWidth', toggleScreenWidthContent.classList.contains('max-w-7xl') ? "false" : "true");
}
