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
| `altcha-default.php` | Force les 4 protections WordPress gratuites (connexion, inscription, réinitialisation de mot de passe, commentaires), la difficulté du proof-of-work sur `high`, et la langue du widget d'après `get_locale()`. Appliqué à la lecture, par filtre (voir la note sur la persistance). |
| `altcha-elementor.php` | Ajoute un type de champ **ALTCHA** dans le form builder Elementor Pro (opt-in par formulaire, validation serveur, payload exclu des emails). |
| `altcha-elementor-atomic.php` | Protège les formulaires atomiques de l'Editor V4 (`e-form`) : widget injecté avant le bouton submit + validation serveur avant le handler Elementor. |
| `altcha-custom-forms.php` | Helpers pour les formulaires codés main : `altcha_custom_widget()` et `altcha_custom_verify()`. |
| `altcha-events.php` | Remonte nos propres vérifications (`bot`, `failed`, `verified`) dans le tableau de bord Altcha, qui ne voit sinon que son propre intercepteur. |
| `altcha-ratelimit.php` | Limite par IP : 10 soumissions/minute et 30 challenges/minute, en 429 avec les en-têtes `X-RateLimit-*`. Réutilise la méthode `rate_limit()` du plugin officiel, publique et sans licence. |

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

**Limiter le débit d'une action custom** — `altcha_custom_rate_limit()` est publique, aucune modification du plugin n'est nécessaire :

```php
add_action('wp_ajax_nopriv_mon_action', function () {
  if (!altcha_custom_rate_limit('mon_action', 5, MINUTE_IN_SECONDS)) {
    wp_send_json_error(['message' => 'Trop de tentatives.'], 429);
  }
}, 1);
```

`altcha_custom_verify($payload)` accepte aussi un payload explicite (REST, champ renommé…). Fail-closed : sans plugin Altcha actif ou sans payload, retourne `false`.

## Notes techniques

- La vérification passe toujours par `AltchaPlugin::verify()` du plugin officiel (anti-rejeu inclus). Toujours lui passer un tableau `$params` : l'appeler sans deuxième argument déclenche un fatal (bug Altcha 3.2.0).
- Le module atomic forms d'Elementor Pro est en `RELEASE_STATUS_DEV` : le contrat `data-interaction-id` et l'action ajax `elementor_pro_atomic_forms_send_form` peuvent changer à une mineure. Re-tester à chaque montée de version d'Elementor Pro (vérifié de 4.0.4 à 4.2.1).
- La difficulté `high` ne change pas le coût par itération (PBKDF2 reste à 5000) mais élargit la plage du compteur : 3,5x plus de travail que `low`. Mesuré : ~1,8 s en navigateur contre ~0,55 s, et ~18 s pour un bot PHP mono-thread contre ~5 s.
- **Résolution de l'IP client.** `AltchaPlugin::get_ip_address()` renvoie la première valeur de `X-Forwarded-For` — un en-tête fourni par le client — dès que `trustedProxies` est vide, ce qui est son réglage par défaut. Un attaquant obtient alors un compteur neuf à chaque requête et contourne toute limite. Le rate limiting n'utilise donc pas cette méthode : il se rabat sur `REMOTE_ADDR` tant que `trustedProxies` n'est pas renseigné (`altcha_custom_client_ip()`).
  - **Sans proxy** (cas le plus courant) : rien à faire, `REMOTE_ADDR` est l'IP réelle. Renseigner `trustedProxies` avec une valeur inoffensive comme `127.0.0.1` sécurise en plus le reste du plugin officiel (pare-feu, journal d'événements), qui reste sinon exposé à l'usurpation.
  - **Derrière un CDN ou un reverse proxy** : renseigner `trustedProxies` avec les plages du proxy est **obligatoire**, sinon toutes les requêtes sont comptées sur l'IP du proxy et les visiteurs se bloquent mutuellement.
- Le garde des formulaires atomiques retire le champ ALTCHA du POST avant Elementor : un formulaire ne contenant *que* le captcha serait donc rejeté comme invalide (aucun formulaire réel n'est dans ce cas).
- **Incompatibilité connue** : ce retrait a lieu avant que le contrôleur ne construise ses données, donc avant le filtre `elementor_pro/atomic_forms/spam_check`. Tout autre code branché sur ce filtre et cherchant le champ `altcha` ne le trouvera pas — typiquement une seconde implémentation ALTCHA tournant en parallèle. Pour le désactiver : `add_filter('altcha_custom_strip_payload', '__return_false')`, au prix d'une ligne `altcha :` dans les emails et les soumissions enregistrées. Il n'existe pas de troisième voie : les données sont figées avant `spam_check`.
- Les hooks sur l'action ajax atomique ont des priorités explicites — rate limit à 1, vérification à 5 — pour ne pas dépendre de l'ordre des `require_once`.
- L'événement `statechange` du widget **ne remonte pas** (`bubbles: false`) : le miroir JS écoute en phase de capture, ce qui couvre aussi les formulaires injectés après le chargement (popups).
- Un réglage forcé (protections, `powComplexity`, langue) modifié dans l'admin ALTCHA sera enregistré en base mais restera sans effet, écrasé par le filtre à la lecture.
- **Les valeurs forcées finissent par être persistées en base.** Le filtre s'applique à la lecture, mais le plugin officiel relit puis réécrit ses réglages lors de ses migrations (`ensure_default_options()`, déclenchée après chaque mise à jour d'ALTCHA) : les valeurs forcées passent alors par `get_settings()` et sont écrites par `update_option()`. Vérifié en conditions réelles — `powComplexity` passé de `low` à `high` en base après une migration simulée. Conséquences : désactiver ce plugin ne restaure pas les réglages d'origine de l'administrateur, et un site réglé sur `very_high` serait rabaissé à `high`. L'effet est fail-secure (les valeurs forcées vont toutes vers plus de protection), aucun contournement n'en découle.
- La rétention des événements est plafonnée à 7 jours sans licence Altcha.

## Tests

Suite de vérification manuelle (site local) : réglages forcés, difficulté effective, les trois intégrations de formulaires (payload absent / inventé / valide / rejeu), la journalisation et les deux limites de débit. Dernier passage : 34/34 sur `main`.

## Installation

```bash
cd wp-content/plugins/   # ou web/app/plugins/ en Bedrock
git clone https://github.com/WarLay0/plugin-altcha-custom.git
wp plugin activate plugin-altcha-custom
```
