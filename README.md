# Altcha Custom Plugin

Ajoute des fonctionnalités gratuites et personnalisables par-dessus le plugin officiel [ALTCHA](https://altcha.org/) (captcha proof-of-work, sans cookie, sans service externe).

## Prérequis

- WordPress 6.5+ (le plugin déclare `Requires Plugins: altcha`)
- Plugin officiel [ALTCHA](https://github.com/altcha-org/altcha-wordpress-next) v3.2+ installé et actif
- PHP 8.1+
- Elementor Pro 3.5+ (optionnel, uniquement pour les features Elementor)

## Fonctionnalités

Un fichier = une feature, dans `includes/` :

| Fichier | Feature |
|---|---|
| `altcha-default.php` | Force les 4 protections WordPress gratuites (connexion, inscription, réinitialisation de mot de passe, commentaires), même si elles sont décochées dans l'admin Altcha. |
| `altcha-elementor.php` | Ajoute un type de champ **ALTCHA** dans le form builder Elementor Pro (opt-in par formulaire, validation serveur, payload exclu des emails). |
| `altcha-elementor-atomic.php` | Protège les formulaires atomiques de l'Editor V4 (`e-form`) : widget injecté avant le bouton submit + validation serveur avant le handler Elementor. |
| `altcha-custom-forms.php` | Helpers pour les formulaires codés main : `altcha_custom_widget()` et `altcha_custom_verify()`. |

## Usage

**Elementor Pro (classique)** : dans le form builder, ajouter un champ et choisir le type **ALTCHA**. Rien d'autre à configurer.

**Elementor Editor V4 (atomique)** : rien à faire, tous les formulaires `e-form` sont protégés automatiquement. Le widget n'apparaît pas dans l'aperçu de l'éditeur (limitation du canvas V4), uniquement sur la page publiée.

**Formulaire custom** :

```php
<form method="post" action="...">
  <!-- vos champs -->
  <?php echo altcha_custom_widget(); ?>
  <button>Envoyer</button>
</form>
```

```php
// Dans le handler
if (!altcha_custom_verify()) {
  wp_die('Captcha invalide', 403);
}
```

`altcha_custom_verify($payload)` accepte aussi un payload explicite (REST, champ renommé…). Fail-closed : sans plugin Altcha actif ou sans payload, retourne `false`.

## Notes techniques

- La vérification passe toujours par `AltchaPlugin::verify()` du plugin officiel (anti-rejeu inclus). Toujours lui passer un tableau `$params` : l'appeler sans deuxième argument déclenche un fatal (bug Altcha 3.2.0).
- Le module atomic forms d'Elementor Pro est en `RELEASE_STATUS_DEV` : le contrat `data-interaction-id` et l'action ajax `elementor_pro_atomic_forms_send_form` peuvent changer à une mineure. Re-tester à chaque montée de version d'Elementor Pro (testé avec 4.2.1).

## Installation

```bash
cd wp-content/plugins/   # ou web/app/plugins/ en Bedrock
git clone https://github.com/WarLay0/plugin-altcha-custom.git
wp plugin activate plugin-altcha-custom
```
