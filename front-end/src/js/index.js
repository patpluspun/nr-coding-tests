document.addEventListener('DOMContentLoaded', () => {
  // Handles navigation menu.
  const button = document.getElementById('open-nav');
  const menu = document.getElementById('nav-menu');

  button.addEventListener('click', () => {
    menu.classList.toggle('hidden');
  });

  // Opens up search field.
  const searchButton = document.getElementById('search');
  const searchInput = document.getElementById('search-area');

  searchButton.addEventListener('click', (event) => {
    event.stopPropagation();

    searchInput.classList.toggle('hidden');

    if (!searchInput.classList.contains('hidden')) {
      searchInput.focus();
    }
  });

  // Closes nav and search if clicked outside.
  document.addEventListener('click', (event) => {
    const insideMenu = button.contains(event.target) || menu.contains(event.target);
    const insideSearch = searchInput.contains(event.target) || searchButton.contains(event.target);

    if (!insideMenu) {
      menu.classList.add('hidden');
    }
    if (!insideSearch) {
      searchInput.classList.add('hidden');
    }
  });

});