document.addEventListener('DOMContentLoaded', () => {
  // Handles navigation menu.
  const button = document.getElementById('open-nav');
  const menu = document.getElementById('nav-menu');

  button.addEventListener('click', () => {
    menu.classList.toggle('hidden');
  });

  // Opens up search field.
  const searchTriggers = document.querySelectorAll('.search-trigger');
  const searchInput = document.getElementById('search-area');

  searchTriggers.forEach(trigger => {
    trigger.addEventListener('click', (event) => {
      event.stopPropagation();

      searchInput.classList.toggle('hidden');

      if (!searchInput.classList.contains('hidden')) {
        searchInput.focus();
      }
    });
  });

  // Closes nav and search if clicked outside.
  document.addEventListener('click', (event) => {
    const insideMenu = button.contains(event.target) || menu.contains(event.target);
    const isTrigger = Array.from(searchTriggers).some(t => t.contains(event.target));

    if (!insideMenu) {
      menu.classList.add('hidden');
    }
    if (!searchInput.contains(event.target) && !isTrigger) {
      searchInput.classList.add('hidden');
    }
  });

  // Adds special classes to all buttons.
  const allButtons = document.querySelectorAll('button');
  const width = window.innerWidth;

  allButtons.forEach(button => {
    if (width < 768) {
      button.classList.add('mobile');
    }
    else if (width >= 768 && width < 1024) {
      button.classList.add('tablet');
    }
  });

});