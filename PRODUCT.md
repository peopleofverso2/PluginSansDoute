# SansDoute Influence — Le marketing d'influence par le contenu

## Le problème (sans bullshit)

Le marketing d'influence est cassé. Voici pourquoi :

| Ce qu'on fait aujourd'hui | Pourquoi c'est nul |
|---|---|
| On paie pour des posts qui disparaissent en 24h | Zéro valeur durable |
| On match sur le nombre d'abonnés | Ça ne prédit rien sur la qualité |
| On mesure des likes et impressions | Ce sont des vanity metrics — personne ne lit |
| On envoie un brief par email | Le créateur ne le lit pas, ou l'interprète mal |
| On vérifie manuellement la compliance ARPP | Personne ne le fait → risque juridique |
| Le contenu sonne faux | Parce que le créateur copie-colle les talking points de la marque |
| La marque ne peut pas réutiliser le contenu | Elle paie pour un usage unique |

## L'insight

**Le meilleur marketing d'influence, c'est du content marketing co-créé.**

Pas un post sponsorisé jetable. Un article, un guide, un contenu qui dure — écrit par un créateur dont la voix est authentique, formaté comme un pro, conforme à la loi, et réutilisable par la marque.

## Le produit : SansDoute Influence

Un module WordPress qui transforme le pipeline AI Formatter existant en **plateforme de co-création marque × créateur**.

### Les 4 features qui comptent (et pourquoi)

---

### 1. Brief structuré avec garde-fous

**Pourquoi :** 80% des allers-retours marque/créateur viennent d'un brief mal compris.

Un brief n'est pas un PDF de 15 pages. C'est une checklist machine-readable :

- **Thématique** : de quoi on parle
- **Mots-clés obligatoires** : ce qui doit apparaître dans le contenu
- **Mots-clés interdits** : ce qu'on ne veut surtout pas (concurrents, termes sensibles)
- **Liens obligatoires** : URLs avec UTM pré-configurés
- **Ton attendu** : formel / journalistique / gonzo / corporate (détecté automatiquement via le tone detector existant)
- **Mentions légales** : ce que la loi ARPP exige
- **Deadline + date de publication**
- **Budget** : transparent, pas de négociation floue

Le brief est un Custom Post Type. Le créateur le voit en temps réel quand il écrit.

---

### 2. Compliance automatique (ARPP + brief)

**Pourquoi :** En France, la loi oblige à mentionner les partenariats commerciaux. L'ARPP (Autorité de Régulation Professionnelle de la Publicité) exige des mentions explicites. Quasiment personne ne vérifie — jusqu'au jour où l'amende arrive.

Le module vérifie automatiquement :

- **Mentions légales ARPP** : présence de « En partenariat avec X », « #pub », « #sponsorisé », « Contenu sponsorisé »
- **Position de la mention** : doit être visible dès le début (pas planquée en bas)
- **Liens obligatoires** : tous les liens du brief sont présents
- **Mots-clés obligatoires** : tous les mots-clés du brief sont couverts
- **Mots-clés interdits** : aucun mot-clé interdit n'est utilisé

Résultat : un **score de compliance** en temps réel (0-100) avec des recommandations concrètes.

---

### 3. Scoring d'authenticité et qualité

**Pourquoi :** Le contenu sponsorisé qui sonne faux performe 3x moins bien que le contenu authentique. Si le créateur copie-colle les talking points de la marque, c'est de l'argent jeté.

Le scoring analyse :

- **Authenticité** : le contenu ressemble-t-il à du langage naturel ou à du copywriting corporate ?
  - Ratio de phrases uniques vs formules marketing clichées
  - Diversité du vocabulaire
  - Présence de la voix personnelle (je, mon expérience, etc.)
  - Détection des « tics publicitaires » (superlatifs, promesses vagues)

- **Lisibilité** : le contenu est-il facile à lire ?
  - Longueur moyenne des phrases (cible : 15-20 mots)
  - Indice de lisibilité simplifié
  - Ratio de mots complexes

- **SEO basique** : le contenu est-il trouvable ?
  - Présence des mots-clés dans les headings
  - Structure de headings correcte (H2 > H3)
  - Longueur du contenu (min 800 mots pour du contenu durable)
  - Présence de liens internes/externes

Résultat : un **score global** (0-100) décomposé en sous-scores, avec des suggestions.

---

### 4. Workspace créateur avec feedback temps réel

**Pourquoi :** Le créateur ne devrait pas écrire dans Google Docs puis copier-coller. Il devrait écrire dans un espace où il voit le brief, le score de compliance, et le score de qualité en temps réel.

Le workspace :

- **Brief visible** en sidebar (toujours accessible)
- **Éditeur de texte** connecté au pipeline AI Formatter (nettoyage, typographie, preview)
- **Barre de compliance** : score mis à jour à chaque modification
- **Barre de qualité** : score mis à jour à chaque modification
- **Workflow** : Brouillon → Soumis → Révision → Approuvé → Publié
- **Feedback marque** : commentaires inline (via le système de commentaires WordPress)

---

## Architecture technique

### S'appuie sur l'existant
- Pipeline de formatage (clean → typography → AI proofread)
- Tone detector (analyse heuristique du ton)
- AI provider (OpenAI / Anthropic)
- Système de presets CSS

### Ajoute
- 2 Custom Post Types : `aif_brief` (briefs) et `aif_collab` (contenus)
- 5 endpoints REST API
- 1 page admin (dashboard campagnes)
- 1 page admin (workspace créateur)
- Compliance checker (ARPP + brief)
- Content scorer (authenticité + lisibilité + SEO)

### Data model

**`aif_brief`** (Custom Post Type)
```
post_title    = Nom de la campagne
post_content  = Description du brief
post_author   = Brand manager (WordPress user)
post_status   = draft | publish | closed

Meta :
  _aif_brand              string    Nom de la marque
  _aif_keywords_required  array     Mots-clés obligatoires
  _aif_keywords_forbidden array     Mots-clés interdits
  _aif_links_required     array     Liens obligatoires (URLs)
  _aif_tone               string    Ton attendu
  _aif_legal_mentions     array     Mentions légales requises
  _aif_deadline           string    Date limite de livraison
  _aif_publish_date       string    Date de publication prévue
  _aif_budget             string    Budget par contenu
```

**`aif_collab`** (Custom Post Type)
```
post_title    = Titre du contenu
post_content  = Le contenu du créateur
post_author   = Créateur (WordPress user)
post_status   = publish (toujours, le statut réel est dans la meta)

Meta :
  _aif_brief_id           int       ID du brief lié
  _aif_collab_status      string    draft | submitted | revision | approved | published
  _aif_compliance_result  array     Dernier résultat compliance (cached)
  _aif_scoring_result     array     Dernier résultat scoring (cached)
```

### REST API

```
POST /wp-json/ai-formatter/v1/influence/briefs          Créer un brief
GET  /wp-json/ai-formatter/v1/influence/briefs           Lister les briefs
GET  /wp-json/ai-formatter/v1/influence/briefs/{id}      Détail d'un brief

POST /wp-json/ai-formatter/v1/influence/collabs          Créer/mettre à jour un contenu
GET  /wp-json/ai-formatter/v1/influence/collabs           Lister les contenus
PATCH /wp-json/ai-formatter/v1/influence/collabs/{id}/status  Changer le statut

POST /wp-json/ai-formatter/v1/influence/check-compliance Vérifier la compliance d'un contenu
POST /wp-json/ai-formatter/v1/influence/score-content    Scorer un contenu
```

---

## Ce que ce produit n'est PAS

- **Pas un marketplace d'influenceurs.** On ne fait pas de matching. Les marques et créateurs se trouvent ailleurs. Nous, on les aide à travailler ensemble efficacement.
- **Pas un outil d'analytics.** On ne track pas les likes ou les impressions. On s'assure que le contenu est bon AVANT publication.
- **Pas un CRM.** On ne gère pas les relations. On gère le contenu.
- **Pas un outil de paiement.** Le budget est indicatif. Le paiement se fait ailleurs.

## Ce que ce produit EST

Un outil qui répond à une seule question : **ce contenu sponsorisé est-il bon, authentique et légal ?**

Si oui → publie.
Si non → voici exactement ce qu'il faut corriger.

---

*SansDoute Influence v0.1 — Module pour AI Formatter WordPress Plugin*
