/**
 * Progressive enhancement for Summit Wishlist. Without JavaScript every control
 * is a plain form POST; with it, "Add to wishlist" posts in the background and
 * shows a toast. SummitWishlist.add() is the contract a future list picker calls.
 */
(function () {
  'use strict';

  function toast(messages, success) {
    var container = document.querySelector('.summitwishlist-toasts');
    if (!container) {
      container = document.createElement('div');
      container.className = 'summitwishlist-toasts';
      container.setAttribute('role', 'status');
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }
    var node = document.createElement('div');
    node.className = 'summitwishlist-toast ' + (success ? 'is-success' : 'is-error');
    node.textContent = messages.join(' ');
    container.appendChild(node);
    setTimeout(function () { node.remove(); }, 4000);
  }

  function updateCount(count) {
    if (typeof count !== 'number') {
      return;
    }
    document.querySelectorAll('.js-summitwishlist-count').forEach(function (el) {
      el.textContent = String(count);
    });
  }

  /**
   * @param {string} url  module item endpoint
   * @param {FormData} data  must contain swl_token, id_product and either group[] or swl_id_product_attribute
   * @param {{wishlistId?: number}} [options]  reserved for the multi-list picker
   */
  function add(url, data, options) {
    data.set('op', 'add');
    data.set('ajax', '1');
    if (options && options.wishlistId) {
      data.set('id_wishlist', String(options.wishlistId));
    }

    return fetch(url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        toast(json.messages || [], !!json.success);
        updateCount(json.count);

        return json;
      });
  }

  // Delegated so buttons re-rendered by AJAX (combination switch, listing filters) keep working.
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-summitwishlist-add');
    if (!button) {
      return;
    }
    var form = button.form || button.closest('form');
    if (!form) {
      return;
    }
    event.preventDefault();

    var url = button.getAttribute('formaction') || form.getAttribute('action');
    var data = new FormData(form);
    // On the product page the button lives inside the core cart form; keep only what we need.
    data.delete('token');
    data.delete('qty');

    button.disabled = true;
    add(url, data)
      .catch(function () { toast(['Something went wrong. Please try again.'], false); })
      .finally(function () { button.disabled = false; });
  });

  // Wishlist page: plain form posts; block double submits.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.classList || !form.classList.contains('summitwishlist-page')) {
      return;
    }
    setTimeout(function () {
      form.querySelectorAll('.js-summitwishlist-submit').forEach(function (b) { b.disabled = true; });
    }, 0);
  });

  window.SummitWishlist = { add: add };
})();
