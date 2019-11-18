
(({ behaviors }) => {
  behaviors.pgWidget = {

    debounce: (func, wait) => {
      if (window.pgWidgetTimeout) {
        clearTimeout(window.pgWidgetTimeout);
      }
      window.pgWidgetTimeout = setTimeout(func, wait);
    },

    attach: (context) => {

      context.querySelectorAll('[data-toggle]').forEach((obj) => {
        let elementClassSelector = obj.getAttribute('data-toggle');
        let statusIndicator = obj.parentNode.querySelector('[type="hidden"]');
        let element = obj.parentNode.querySelector('.' + elementClassSelector);
        if (statusIndicator.value === 'open') {
          element.classList.add('pg-open');
        }

        obj.addEventListener('click', (e) => {
          e.preventDefault();
          behaviors.pgWidget.debounce(() => {
              if (element.classList.contains('pg-open')) {
                element.classList.remove('pg-open');
                statusIndicator.value = '';
              } else {
                element.classList.add('pg-open');
                statusIndicator.value = 'open';
              }
            }, 200);
        });
      });

    }
  };
})(Drupal);