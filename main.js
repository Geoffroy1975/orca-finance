(function () {
  'use strict';

  // Année du pied de page
  document.querySelectorAll('.annee').forEach(function (el) { el.textContent = new Date().getFullYear(); });

  // En-tête : filet au défilement
  var entete = document.getElementById('entete');
  if (entete) {
    var majEntete = function () { entete.classList.toggle('defile', window.scrollY > 8); };
    majEntete();
    window.addEventListener('scroll', majEntete, { passive: true });
  }

  // Menu mobile
  var boutonMenu = document.querySelector('.menu-bouton');
  var nav = document.getElementById('navigation');
  if (boutonMenu && nav) {
    var fermer = function () { nav.classList.remove('ouvert'); boutonMenu.setAttribute('aria-expanded', 'false'); };
    boutonMenu.addEventListener('click', function () {
      var ouvert = nav.classList.toggle('ouvert');
      boutonMenu.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
    nav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', fermer); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermer(); });
  }

  // Formulaire de contact
  var form = document.getElementById('formulaire-contact');
  if (!form) return;
  var retour = document.getElementById('retour-formulaire');
  var jeton = document.getElementById('jeton');
  var bouton = form.querySelector('button[type="submit"]');
  if (jeton) jeton.value = String(Date.now());

  var afficher = function (texte, erreur) {
    retour.textContent = texte;
    retour.classList.toggle('erreur', !!erreur);
    retour.hidden = false;
  };

  // Retour après envoi sans JavaScript (redirection depuis contact.php)
  var params = new URLSearchParams(window.location.search);
  if (params.get('envoi') === 'ok') afficher('Demande envoyée. Nous revenons vers vous rapidement.', false);
  if (params.get('envoi') === 'erreur') afficher("La demande n’a pas pu être envoyée. Vérifiez les champs ou écrivez-nous à contact@orca-finance.fr.", true);

  var messages = {
    nom: 'Indiquez votre nom et prénom.',
    societe: 'Indiquez le nom de votre société.',
    email: 'Indiquez une adresse e-mail valide, par exemple nom@societe.fr.',
    message: 'Décrivez votre demande en quelques lignes.',
    consentement: 'Cochez la case pour accepter le traitement de votre demande.'
  };

  var valider = function () {
    var premier = null;
    form.querySelectorAll('[required]').forEach(function (champ) {
      var ok = champ.type === 'checkbox' ? champ.checked : champ.value.trim() !== '' && champ.checkValidity();
      champ.setAttribute('aria-invalid', ok ? 'false' : 'true');
      if (!ok && !premier) premier = champ;
    });
    if (premier) {
      afficher(messages[premier.name] || 'Complétez les champs obligatoires.', true);
      premier.focus();
      return false;
    }
    return true;
  };

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!valider()) return;
    bouton.disabled = true;
    bouton.textContent = 'Envoi en cours…';
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (data && data.ok) {
          form.reset();
          if (jeton) jeton.value = String(Date.now());
          afficher('Demande envoyée. Nous revenons vers vous rapidement.', false);
        } else {
          afficher((data && data.message) || "La demande n’a pas pu être envoyée. Écrivez-nous à contact@orca-finance.fr.", true);
        }
      })
      .catch(function () {
        afficher("La demande n’a pas pu être envoyée. Vérifiez votre connexion ou écrivez-nous à contact@orca-finance.fr.", true);
      })
      .finally(function () {
        bouton.disabled = false;
        bouton.textContent = 'Envoyer la demande';
      });
  });
})();
