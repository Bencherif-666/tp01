<?php
// nationalite/nationalite.php : formulaire de saisie d'une nationalité (avec drapeau)
require_once __DIR__ . '/../includes/fonctions.php';
$base  = '../';
$titre = 'Gestion des nationalités';
require __DIR__ . '/../includes/header.php';
?>
<style>
    .apercu-drapeau { display: flex; align-items: center; gap: 10px; min-height: 48px; }
    .apercu-drapeau img { height: 40px; border: 1px solid #ccc; display: none; }
    .apercu-drapeau small { color: #555; }
</style>

<!-- enctype multipart/form-data : nécessaire pour l'envoi optionnel d'une image -->
<form id="form-nationalite" method="post" action="enregistrer.php" enctype="multipart/form-data">
    <table class="formulaire">
        <tr>
            <td>CODE</td>
            <td><input type="text" name="code" id="code" maxlength="3" size="5"></td>
        </tr>
        <tr>
            <td>NATIONALITE</td>
            <td><input type="text" name="libelle" id="libelle" maxlength="40" size="40"></td>
        </tr>
        <tr>
            <td>DRAPEAU</td>
            <td>
                <div class="apercu-drapeau">
                    <img id="apercu-img" alt="Aperçu du drapeau">
                    <small id="apercu-texte">Tapez un code (ex : DZ) : le drapeau est recherché automatiquement.</small>
                </div>
            </td>
        </tr>
        <tr>
            <td>IMAGE (optionnel)</td>
            <td>
                <input type="file" name="drapeau_fichier" id="drapeau_fichier"
                       accept="image/png,image/jpeg,image/gif,image/webp">
                <br><small>Si vous choisissez une image, elle remplace celle trouvée par l'API.</small>
            </td>
        </tr>
    </table>
    <button type="submit">Enregistrer</button>
    <!-- formnovalidate + formaction : ce bouton va directement vers la liste -->
    <button type="submit" formaction="liste.php" formmethod="get" formnovalidate>Affichage Liste</button>
</form>

<script>
(function () {
    var champCode  = document.getElementById('code');
    var champFile  = document.getElementById('drapeau_fichier');
    var img        = document.getElementById('apercu-img');
    var texte      = document.getElementById('apercu-texte');
    var minuteur   = null;
    var texteDefaut = texte.textContent;

    function afficher(src, message) {
        if (src) { img.src = src; img.style.display = 'block'; }
        else     { img.removeAttribute('src'); img.style.display = 'none'; }
        texte.textContent = message;
    }

    // Recherche du drapeau via l'API restcountries.com (aperçu uniquement)
    function chercherDrapeau() {
        var code = champCode.value.trim();
        if (champFile.files.length) { return; }          // une image manuelle est choisie
        if (!/^[A-Za-z]{2,3}$/.test(code)) { afficher(null, texteDefaut); return; }

        texte.textContent = 'Recherche du drapeau...';
        fetch('https://restcountries.com/v3.1/alpha/' + encodeURIComponent(code) + '?fields=name,flags')
            .then(function (r) { if (!r.ok) { throw new Error('introuvable'); } return r.json(); })
            .then(function (data) {
                var pays = Array.isArray(data) ? data[0] : data;
                afficher(pays.flags.png, 'Pays trouvé : ' + pays.name.common);
            })
            .catch(function () {
                afficher(null, 'Aucun drapeau trouvé pour ce code (vous pouvez envoyer une image).');
            });
    }

    champCode.addEventListener('input', function () {
        clearTimeout(minuteur);
        minuteur = setTimeout(chercherDrapeau, 500);
    });

    // Aperçu de l'image choisie manuellement
    champFile.addEventListener('change', function () {
        if (champFile.files.length) {
            afficher(URL.createObjectURL(champFile.files[0]), 'Image choisie : ' + champFile.files[0].name);
        } else {
            chercherDrapeau();
        }
    });
})();
</script>
<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
