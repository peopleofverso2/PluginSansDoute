# AI Formatter - Plugin WordPress

**par [Peopleofverso](https://github.com/peopleofverso2)**

Plugin WordPress d'edition intelligente pour [SansDoute](https://github.com/peopleofverso2/PluginSansDoute). Collez un texte, il le nettoie, corrige la typographie francaise, et le met en forme. Respecte la voix des auteurs -- ne reecrit jamais, corrige uniquement.

## Fonctionnalites

### Pipeline de traitement (3 couches)

**Layer A -- Nettoyage LLM (sans IA)**
- Supprime les introductions cliches : "Bien sur !", "Voici...", "Je vais vous expliquer...", "C'est une excellente question !"
- Supprime les conclusions cliches : "En resume", "N'hesitez pas...", "Je reste a votre disposition"
- Remplace les emojis de checklist par des puces propres
- Aplatit les titres abusifs (####+ -> ###)
- Supprime les "Introduction" / "Conclusion" / "FAQ" factices
- Supprime les separateurs inutiles (---, ===, ***)
- Normalise les sauts de ligne multiples
- **Mode "Typographie seule"** : desactive cette couche pour les textes d'auteurs

**Layer B -- Typographie francaise**
- Espaces fines insecables (U+202F) avant ; ? !
- Espaces insecables (U+00A0) avant :
- Guillemets francais << ... >> avec espaces insecables
- Apostrophes courbes typographiques
- Tirets cadratin (---) et demi-cadratin (--)
- Points de suspension typographiques
- Ligatures oe automatiques (coeur, oeuvre, soeur, voeux, etc.)
- Espace apres ponctuation si manquant
- Protection des URLs et du code inline (pas de modifications)
- Nettoyage des doubles espaces

**Layer C -- Correction IA (optionnelle)**
- Correction orthographe, grammaire, accords
- 4 registres : neutre, journalistique, gonzo-pro, corporate
- **Respecte absolument la voix de l'auteur** -- ne reformule jamais
- Providers : OpenAI ou Anthropic (configurable)
- Sortie sanitizee via `wp_kses_post()`
- Temperature basse (0.15) pour des corrections fideles

### Traduction (langues europeennes)

Traduisez les articles formates vers 21 langues europeennes :

| Langue | Code | Langue | Code |
|--------|------|--------|------|
| English | en | Polski | pl |
| Espanol | es | Romana | ro |
| Deutsch | de | Svenska | sv |
| Italiano | it | Dansk | da |
| Portugues | pt | Suomi | fi |
| Nederlands | nl | Ellinika | el |
| Cestina | cs | Magyar | hu |
| Hrvatski | hr | Bulgarski | bg |
| Slovencina | sk | Slovenscina | sl |
| Eesti | et | Latviesu | lv |
| Lietuviu | lt | | |

Prompt de traduction optimise : traduction naturelle (pas de mot-a-mot), respect du style de l'auteur, adaptation des expressions idiomatiques, typographie de la langue cible.

### Interface

- **Page admin** : Outils -> AI Formatter (formatage + traduction)
- **Panneau Gutenberg** : sidebar avec formatage + traduction integres
- **Preview live** avec choix de preset CSS
- **Drag & drop** de fichiers .txt / .md
- **Copier HTML** en un clic
- **Statistiques** : compteur de mots et caracteres en temps reel
- **Notifications toast** (pas d'alertes intrusives)
- **Raccourci** : Ctrl+Entree pour lancer le formatage
- **3 modes** : Nettoyage LLM, Typographie seule (auteur), IA correction

### Presets CSS

| Preset | Description | Typographie |
|--------|-------------|-------------|
| **Clean** | Moderne, lisible | System UI, 15px, line-height 1.6 |
| **SansDoute** | Editorial, elegant | Georgia serif, 16.5px, line-height 1.78 |
| **Doc technique** | Code-friendly | Monospace, 13.5px, line-height 1.65 |
| **Apple clean** | Minimaliste Apple | SF Pro, 17px, line-height 1.53 |

Les presets sont stockes en post meta (`_aif_css_preset`) et appliques automatiquement sur les articles publies via une body class `aif-preset-{slug}`.

## Installation

1. Copiez le dossier `ai-formatter/` dans `wp-content/plugins/`
2. Activez le plugin dans l'admin WordPress
3. (Optionnel) Configurez la cle API dans **Reglages > AI Formatter**

```
wp-content/plugins/ai-formatter/
```

## Configuration IA

Allez dans **Reglages > AI Formatter** :

| Option | Description | Defaut |
|--------|-------------|--------|
| Provider | OpenAI ou Anthropic | OpenAI |
| Cle API | Votre cle API (stockee en base, jamais exposee cote client) | -- |
| Modele | Modele a utiliser | `gpt-4o-mini` (OpenAI) / `claude-sonnet-4-20250514` (Anthropic) |

La cle API est uniquement utilisee cote serveur (appel HTTP WordPress -> API du provider). Elle n'est jamais envoyee au navigateur.

## Utilisation

### Via la page admin (Outils > AI Formatter)

1. Collez votre texte dans le champ de gauche (ou glissez un fichier .txt/.md)
2. Choisissez le mode :
   - **Nettoyage LLM + typographie** : pour les textes issus de ChatGPT/Claude/etc.
   - **Typographie seule** : pour les textes d'auteurs (pas de nettoyage LLM)
   - **IA : correction orthographe** : appel au provider configure
3. Si mode IA, choisissez le registre (neutre, journalistique, gonzo-pro, corporate)
4. Choisissez un preset CSS pour la preview
5. Cliquez **Nettoyer & Formater** (ou Ctrl+Entree)
6. Copiez le HTML ou inserez-le dans un article
7. (Optionnel) Traduisez le resultat vers une autre langue europeenne

### Via Gutenberg (sidebar)

1. Ouvrez un article dans l'editeur Gutenberg
2. Dans la sidebar, panneau **AI Formatter**
3. Choisissez le mode, le style, et le preset CSS
4. Cliquez **Formater le contenu**
5. Le contenu de l'article est remplace par la version corrigee
6. Le preset CSS est sauvegarde en post meta
7. Ouvrez le panneau **Traduction** pour traduire l'article

## Architecture

```
ai-formatter/
  ai-formatter.php              # Point d'entree, hooks, REST API, page admin
  includes/
    clean.php                    # Layer A : nettoyage des tics LLM
    typography.php               # Layer B : typographie francaise
    html.php                     # Conversion pseudo-markdown -> HTML
    ai-provider.php              # Appels IA (OpenAI + Anthropic)
    translation.php              # Systeme de traduction (21 langues EU)
    settings.php                 # Page de reglages (cle API, provider)
    gutenberg.php                # Integration sidebar Gutenberg
    frontend.php                 # CSS presets sur le front (articles publies)
  assets/
    admin.css                    # Styles page admin + presets preview
    admin.js                     # JS page admin (REST, preview, traduction)
    gutenberg.js                 # JS sidebar Gutenberg
    frontend.css                 # Presets CSS pour le front-end
```

### REST API

#### Formater du texte

**Endpoint** : `POST /wp-json/ai-formatter/v1/format`

**Parametres (JSON body)** :

```json
{
  "text": "Le texte brut a formater",
  "mode": "clean_only | typo_only | ai_proofread",
  "style": "neutre | journalistique | gonzo | corporate",
  "css": "clean | sansdoute | tech | apple"
}
```

**Reponse** :

```json
{
  "html": "<h2>Titre</h2><p>Texte formate...</p>",
  "css": "clean",
  "stats": { "words": 142, "chars": 856, "chars_no_spaces": 723 }
}
```

#### Traduire du HTML

**Endpoint** : `POST /wp-json/ai-formatter/v1/translate`

**Parametres (JSON body)** :

```json
{
  "html": "<p>Le HTML a traduire</p>",
  "source_lang": "fr",
  "target_lang": "en"
}
```

**Reponse** :

```json
{
  "html": "<p>The HTML to translate</p>",
  "source_lang": "fr",
  "target_lang": "en",
  "target_name": "English"
}
```

**Authentification** : nonce WordPress + `current_user_can('edit_posts')` sur les deux endpoints.

## Securite

- Nonce WordPress sur toutes les requetes REST
- Verification `current_user_can('edit_posts')` sur chaque endpoint
- Cle API stockee en `wp_options`, jamais exposee cote client
- Sortie IA sanitizee via `wp_kses_post()`
- `esc_html()` sur tout le contenu utilisateur avant insertion HTML
- `sanitize_text_field()` sur tous les reglages
- Champ de cle API en `type="password"` avec `autocomplete="off"`

## Compatibilite

- WordPress 5.8+
- PHP 7.4+
- Gutenberg (editeur de blocs) pour l'integration sidebar
- Fonctionne aussi avec l'editeur classique (via la page Outils)

## Feuille de route

- [ ] Import DOCX (via mammoth.js cote client)
- [ ] Detection automatique du ton du texte
- [ ] Historique de versions (diff avant/apres)
- [ ] Presets CSS personnalisables (editeur)
- [ ] Mode "strict" (tout en paragraphes, pas de listes)
- [ ] Export PDF / Markdown
- [x] ~~Support multilingue~~ Traduction vers 21 langues europeennes

## Licence

MIT -- Voir [LICENSE](LICENSE)

---

*AI Formatter v1.0.0 -- par [Peopleofverso](https://github.com/peopleofverso2)*
