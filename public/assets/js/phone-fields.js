/* Laravel: flag + country-code picker on the mobile number of server-rendered forms (partials/phone-field.blade.php,
   "Contact us" and "Become an agent"), as on the booking and account forms. The chosen code goes to the hidden "dial"
   field; after a failed submit the code the visitor chose is shown again. */
(() => {
  document.querySelectorAll('[data-phone-field]').forEach((input) => {
    const dial = input.parentElement.querySelector('[data-phone-dial]');
    SC.phoneWidget(input, dial.value, (code) => { dial.value = code; });
  });
})();
