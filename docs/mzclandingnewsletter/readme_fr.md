# MZC Landing Newsletter — Documentation

## Version 1.0.0

## Auteur : Marcin Zbijowski Consulting

## Compatibilité : PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Table des matières

1. Présentation
2. Prérequis
3. Installation
4. Configuration
5. Préréglages CSS
6. Référence des classes CSS
7. Conformité RGPD
8. Google Tag Manager et analytique
9. Configuration Multi-Boutique
10. Gestion des abonnés
11. Configuration SEO
12. Interaction avec le mode maintenance
13. Contournement administrateur
14. Limitation de fréquence
15. Fonctionnalités de sécurité
16. Traductions
17. Dépannage
18. Désinstallation
19. Support

---

## 1. Présentation

MZC Landing Newsletter ajoute un mode Page de destination à votre boutique PrestaShop. Lorsqu'il est activé, tous les visiteurs du front-office voient une page de marque avec le logo de votre boutique, un message personnalisé et un formulaire d'inscription à la newsletter. Les administrateurs et les adresses IP en liste blanche contournent la page de destination et accèdent normalement à la boutique.

Cette fonctionnalité est indépendante du mode maintenance intégré de PrestaShop. Utilisez-la lorsque votre boutique n'est pas encore prête, pendant une migration, la configuration du catalogue, un changement de marque, ou à tout moment où vous souhaitez collecter des adresses e-mail avant le lancement.

Les abonnés sont enregistrés dans la table native de newsletter PrestaShop (ps_emailsubscription), ils apparaissent donc automatiquement dans vos outils de newsletter existants sans synchronisation ni export.

---

## 2. Prérequis

- PrestaShop 8.0.0 ou ultérieur (compatible jusqu'à 9.x)
- PHP 8.1 ou ultérieur
- Module ps_emailsubscription installé (fourni par défaut avec PrestaShop)
- Module psgdpr installé et configuré (optionnel, pour la case de consentement RGPD)

---

## 3. Installation

### Depuis PrestaShop Addons

1. Téléchargez le fichier ZIP du module depuis votre compte Addons
2. Accédez au back-office de votre PrestaShop
3. Naviguez vers Modules > Gestionnaire de modules
4. Cliquez sur Charger un module
5. Sélectionnez le fichier ZIP et attendez la fin de l'installation
6. Cliquez sur Configurer pour paramétrer le module

### Installation manuelle

1. Extrayez le fichier ZIP
2. Téléchargez le dossier mzclandingnewsletter dans le répertoire modules de votre PrestaShop via FTP
3. Accédez à Modules > Gestionnaire de modules dans le back-office
4. Recherchez MZC Landing Newsletter
5. Cliquez sur Installer, puis Configurer

---

## 4. Configuration

Naviguez vers Modules > Gestionnaire de modules, trouvez MZC Landing Newsletter et cliquez sur Configurer.

### Activer le mode page de destination

Basculez l'option Activer la page de destination sur Oui pour activer la page pour tous les visiteurs. Basculez sur Non pour désactiver et afficher votre boutique normale.

### Message de la page de destination

Saisissez le message affiché sur la page de destination. Ce champ prend en charge :

- L'édition de texte enrichi (gras, italique, liens, formatage)
- Le contenu multilingue — utilisez le sélecteur de langue pour saisir des messages différents par langue
- Le contenu HTML — pour un formatage avancé

Message par défaut : Nous arrivons bientôt ! Notre boutique est en construction. Abonnez-vous à notre newsletter pour être notifié lors du lancement.

### CSS personnalisé

Saisissez des règles CSS personnalisées pour modifier l'apparence de la page de destination. Laissez vide pour utiliser le style par défaut. Voir la Section 6 pour la liste complète des classes CSS disponibles.

---

## 5. Préréglages CSS

Trois préréglages intégrés sont disponibles dans le panneau Préréglages CSS de la page de configuration. Cliquez sur Charger le préréglage pour remplir le champ CSS personnalisé avec les styles du préréglage.

### Modern Dark (Moderne sombre)

Fond en dégradé violet avec un effet glassmorphism sur la carte. Bouton d'abonnement en dégradé violet-bleu, champs de saisie translucides et logo inversé pour les fonds sombres. Idéal pour les marques technologiques, gaming ou lifestyle moderne.

### Modern Light (Moderne clair)

Fond en dégradé chaud du pêche au blanc avec une grande carte arrondie et des ombres profondes. Bouton d'abonnement en dégradé orange avec une typographie élégante et un espacement des lettres. Idéal pour les marques de mode, beauté ou lifestyle.

### Soft Gray (Gris doux)

Fond plat gris clair avec une carte à bordure subtile. Tons gris atténués partout avec un bouton d'abonnement sombre discret. Minimaliste et élégant. Idéal pour les marques professionnelles, B2B ou minimalistes.

Chaque préréglage peut être utilisé tel quel ou modifié davantage dans le champ CSS personnalisé après chargement.

Important : Le chargement d'un préréglage remplace tout CSS personnalisé existant. Si vous avez des styles personnalisés, copiez-les avant de charger un préréglage.

---

## 6. Référence des classes CSS

Les classes CSS suivantes sont disponibles pour la personnalisation. Un tableau de référence complet avec descriptions est affiché dans le panneau Référence des classes CSS de la page de configuration.

### Classes de mise en page

- .mzc-landing-container — wrapper extérieur, couvre tout le viewport, contrôle la couleur de fond ou le dégradé
- .mzc-landing-content — la carte centrée ou boîte de contenu, contrôle max-width, padding, fond, border-radius et ombre

### Logo

- .mzc-landing-logo — div wrapper du logo de la boutique
- .mzc-landing-logo img — l'image du logo elle-même, contrôle la hauteur et largeur maximales

### Message

- .mzc-landing-message — div wrapper pour le titre et le texte du paragraphe
- .mzc-landing-message h1 — le titre principal
- .mzc-landing-message h2 — style de titre alternatif
- .mzc-landing-message h3 — style de titre alternatif
- .mzc-landing-message p — texte du paragraphe sous le titre

### Formulaire

- .mzc-landing-form-wrapper — wrapper pour toute la zone du formulaire
- .mzc-form-group — le conteneur de la ligne champ-bouton
- .mzc-form-input — le champ de saisie e-mail
- .mzc-form-button — le bouton d'abonnement

### Retour d'information et RGPD

- .mzc-form-feedback — la zone de message de succès ou d'erreur sous le formulaire
- .mzc-gdpr-consent — wrapper pour la case de consentement RGPD et le libellé

### Exemple

```css
.mzc-landing-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.mzc-landing-content {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.mzc-form-button {
  background: #764ba2;
  color: #ffffff;
}

.mzc-form-button:hover {
  background: #667eea;
}
```

---

## 7. Conformité RGPD

Le module s'intègre avec le module officiel RGPD de PrestaShop (psgdpr) via trois hooks.

### Case de consentement

Lorsque psgdpr est installé et configuré, une case de consentement avec votre message configuré apparaît sous le champ e-mail sur la page de destination. Le bouton d'abonnement est désactivé tant que le visiteur n'a pas coché la case. Ceci est géré automatiquement par le JavaScript du module psgdpr.

Pour configurer le message de consentement, accédez à Modules > Gestionnaire de modules > Conformité RGPD officielle > Configurer, et paramétrez le message de consentement pour MZC Landing Newsletter.

### Suppression des données

Lorsqu'une demande de suppression de données RGPD est traitée, le module supprime toute adresse e-mail correspondante de la table d'abonnement newsletter et nettoie les enregistrements de limitation de fréquence associés.

### Export des données

Lorsqu'une demande d'export de données RGPD est traitée, le module retourne tous les enregistrements d'abonnement newsletter correspondant à l'adresse e-mail demandée, incluant la date d'abonnement et l'adresse IP d'inscription.

---

## 8. Google Tag Manager et analytique

La page de destination rend trois hooks standard PrestaShop que les modules de suivi utilisent :

- displayHeader — rendu dans la section head du HTML. Utilisé par Google Tag Manager, Google Analytics, Facebook Pixel et modules similaires pour injecter leurs scripts de suivi et l'initialisation du dataLayer.
- displayAfterBodyOpeningTag — rendu immédiatement après la balise body ouvrante. Utilisé par Google Tag Manager pour son iframe noscript de secours.
- displayBeforeBodyClosingTag — rendu avant la balise body fermante. Utilisé par certains modules de suivi pour le chargement différé de scripts.

Cela signifie que tout module de suivi utilisant le système de hooks standard de PrestaShop fonctionnera sur la page de destination sans configuration supplémentaire. Compatibilité vérifiée avec :

- Google Tag Manager (gtmmodule)
- PrestaShop Google Analytics (ps_googleanalytics)
- PrestaShop Marketing with Google (psxmarketingwithgoogle)

---

## 9. Configuration Multi-Boutique

Le module prend entièrement en charge la fonctionnalité multi-boutique de PrestaShop.

### Configuration par boutique

Lorsque le multi-boutique est actif et que vous sélectionnez une boutique spécifique dans le sélecteur de contexte :

- Chaque champ de configuration affiche une case de remplacement
- Cochez la case pour définir une valeur spécifique à la boutique qui remplace la valeur globale par défaut
- Décochez la case pour hériter de la valeur de la configuration Toutes les boutiques

### Scénarios courants

- Activer le mode landing pour une nouvelle boutique tout en gardant les autres actives : Définissez MZC_LANDING_ENABLED sur Non au niveau Toutes les boutiques, puis remplacez par Oui pour la boutique spécifique
- Utiliser des messages différents par boutique : Définissez un message par défaut dans Toutes les boutiques, puis remplacez avec des messages spécifiques où nécessaire
- Utiliser un CSS différent par boutique : Chaque boutique peut avoir son propre style visuel en remplaçant le champ CSS personnalisé

---

## 10. Gestion des abonnés

### Liste des abonnés

Le panneau Abonnés de la page de configuration affiche toutes les adresses e-mail collectées via la page de destination, identifiées par le tag source mzc_landing_page. La liste affiche :

- Adresse e-mail
- Langue au moment de l'abonnement
- Adresse IP d'inscription
- Date d'abonnement

La liste est paginée à 20 entrées par page. Utilisez les liens de navigation en bas pour parcourir.

Cliquez sur Actualiser la liste pour recharger les données des abonnés.

### Export CSV

Cliquez sur Exporter CSV pour télécharger tous les abonnés de la page de destination sous forme de fichier à valeurs séparées par des virgules. L'export inclut tous les abonnés (pas seulement la page actuelle), avec les colonnes : e-mail, langue, IP et date.

### Intégration avec ps_emailsubscription

Puisque le module utilise la table native de newsletter PrestaShop, les abonnés collectés sur la page de destination apparaissent également dans :

- La liste des abonnés du module ps_emailsubscription
- Tout outil d'export de newsletter qui lit la table emailsubscription
- Les intégrations Mailchimp, Sendinblue et autres connectées à PrestaShop

---

## 11. Configuration SEO

La page de destination charge automatiquement les métadonnées SEO depuis la configuration de votre boutique pour la page index (page d'accueil) :

- Meta title — utilisé comme titre de la page HTML
- Meta description — rendu comme balise meta description
- Meta keywords — rendu comme balise meta keywords (si configuré)

Pour configurer ces valeurs, accédez à Paramètres de la boutique > Trafic et SEO > SEO et URL, trouvez la page intitulée index et éditez le meta title, la meta description et les meta keywords.

Si aucun meta title n'est configuré, le module utilise le nom de la boutique par défaut.

La page de destination envoie un code de statut HTTP 503 (Service Unavailable) avec un en-tête Retry-After. Cela indique aux moteurs de recherche que le site est temporairement indisponible et qu'ils doivent revenir plus tard, préservant vos classements existants.

---

## 12. Interaction avec le mode maintenance

Important : Désactivez le mode maintenance intégré de PrestaShop lorsque vous utilisez le mode page de destination.

Le mode maintenance de PrestaShop (Paramètres de la boutique > Général > Maintenance) et le mode page de destination de ce module sont des fonctionnalités indépendantes. Si les deux sont activés simultanément, le mode maintenance de PrestaShop a la priorité car il s'exécute plus tôt dans le cycle de vie de la requête, avant l'exécution du hook de ce module.

Pour accéder aux paramètres de maintenance, allez dans Paramètres de la boutique > Général > Maintenance dans votre back-office et définissez Activer la boutique sur Oui.

Flux de travail recommandé :

1. Désactivez le mode maintenance PrestaShop (définissez Activer la boutique sur Oui)
2. Activez le mode page de destination de MZC Landing Newsletter
3. Travaillez sur votre boutique — vous pouvez y accéder via votre adresse IP en liste blanche
4. Lorsque vous êtes prêt à lancer, désactivez le mode page de destination
5. Votre boutique est immédiatement accessible à tous les visiteurs

---

## 13. Contournement administrateur

Lorsque le mode page de destination est activé, les utilisateurs suivants peuvent toujours accéder à la boutique complète :

### Liste blanche IP

Toute adresse IP listée dans Paramètres de la boutique > Général > Maintenance > IP de maintenance contourne la page de destination. Ajoutez-y votre adresse IP pour travailler sur votre boutique pendant que les visiteurs voient la page de destination. Plusieurs adresses IP peuvent être séparées par des virgules. La notation CIDR est prise en charge (ex. 192.168.1.0/24).

### Administrateurs connectés

Si le paramètre PS_MAINTENANCE_ALLOW_ADMINS est activé, tout utilisateur avec une session active de back-office contourne automatiquement la page de destination. Le module lit le cookie administrateur PrestaShop pour détecter les administrateurs connectés.

---

## 14. Limitation de fréquence

Pour prévenir le spam et les abus, le point de terminaison d'abonnement impose une limite de 3 soumissions par adresse IP par fenêtre de 10 minutes.

Lorsque la limite est dépassée, le visiteur voit un message lui demandant de réessayer plus tard. Le compteur de limite se réinitialise automatiquement après 10 minutes.

Les données de limitation de fréquence (adresses IP et horodatages) sont stockées dans une table de base de données dédiée et automatiquement nettoyées. Les entrées expirées de l'IP actuelle sont purgées à chaque requête, avec un nettoyage global probabiliste de 1% pour empêcher la croissance de la table.

---

## 15. Fonctionnalités de sécurité

### Protection CSRF

Le formulaire d'abonnement inclut un jeton CSRF à rotation temporelle qui change toutes les heures. Les jetons de l'heure actuelle et de l'heure précédente sont acceptés lors de la validation pour éviter les rejets aux limites horaires.

### Protection XSS

Le CSS personnalisé saisi dans le back-office est assaini avant l'enregistrement. Les balises HTML sont supprimées et les séquences de rupture de balise style sont neutralisées pour empêcher l'injection de scripts.

### Content Security Policy

La page de destination envoie un en-tête Content-Security-Policy qui restreint les sources de scripts à self et inline (requis pour les modules de suivi), et autorise les styles depuis self, inline et les sources HTTPS (requis pour les polices web).

### Validation d'e-mail

Les adresses e-mail sont validées en utilisant la méthode intégrée Validate::isEmail() de PrestaShop avant toute opération en base de données.

---

## 16. Traductions

Le module est livré avec des traductions complètes pour 5 langues :

- Anglais (en)
- Polonais (pl)
- Français (fr)
- Espagnol (es)
- Italien (it)

Chaque fichier de traduction couvre les 87 chaînes traduisibles dans la classe du module, le contrôleur d'abonnement et le template de la page de destination.

### Ajouter ou modifier des traductions

Pour traduire le module dans des langues supplémentaires ou modifier les traductions existantes :

1. Accédez à International > Traductions dans votre back-office
2. Sélectionnez Traductions des modules installés dans la liste déroulante Type
3. Sélectionnez la langue cible
4. Trouvez MZC Landing Newsletter dans la liste des modules
5. Saisissez vos traductions et cliquez sur Enregistrer

PrestaShop enregistre automatiquement le fichier de traduction dans modules/mzclandingnewsletter/translations/.

---

## 17. Dépannage

### La page de destination ne s'affiche pas

- Vérifiez que MZC_LANDING_ENABLED est défini sur Oui dans la configuration du module
- Vérifiez que le mode maintenance PrestaShop est désactivé (Paramètres de la boutique > Général > Maintenance > Activer la boutique = Oui)
- Vérifiez que votre IP n'est pas dans la liste blanche IP de maintenance
- Videz le cache PrestaShop (Paramètres avancés > Performances > Vider le cache)

### La case RGPD n'apparaît pas

- Vérifiez que le module psgdpr est installé et activé
- Accédez à la configuration psgdpr et assurez-vous qu'un message de consentement est configuré pour MZC Landing Newsletter
- Videz le cache PrestaShop et rechargez la page de destination

### Les polices ne se chargent pas correctement

- Cela se produit généralement lorsque les polices du thème (ex. Google Fonts) sont chargées via le hook displayHeader. Le module rend ce hook, donc les polices devraient se charger. Sinon, videz le cache de votre navigateur avec Ctrl+Shift+R (Cmd+Shift+R sur Mac)
- Vérifiez la console des outils de développement du navigateur pour les erreurs Content Security Policy

### Les scripts de suivi ne se déclenchent pas

- Vérifiez que votre module de suivi utilise les hooks standard PrestaShop (displayHeader, displayAfterBodyOpeningTag ou displayBeforeBodyClosingTag)
- Vérifiez la console des outils de développement du navigateur pour les erreurs JavaScript
- Certains modules de suivi peuvent nécessiter un contexte de page spécifique non disponible sur la page de destination

### Le bouton d'abonnement ne fonctionne pas

- Vérifiez la console des outils de développement du navigateur pour les erreurs JavaScript
- Vérifiez que la case de consentement psgdpr est cochée (si le RGPD est activé)
- Vérifiez si la limitation de fréquence a été déclenchée (max 3 par IP par 10 minutes)

### Les abonnés n'apparaissent pas dans la liste

- Cliquez sur Actualiser la liste dans la page de configuration
- Vérifiez que vous consultez le bon contexte de boutique dans les configurations multi-boutique
- Vérifiez la table de base de données ps_emailsubscription pour les entrées avec http_referer = mzc_landing_page

---

## 18. Désinstallation

1. Accédez à Modules > Gestionnaire de modules
2. Trouvez MZC Landing Newsletter
3. Cliquez sur la flèche déroulante et sélectionnez Désinstaller
4. Confirmez la désinstallation

Le module va :

- Supprimer toutes les valeurs de configuration (MZC_LANDING_ENABLED, MZC_LANDING_MESSAGE, MZC_LANDING_CSS)
- Supprimer la table de limitation de fréquence (mzc_landing_ratelimit)
- Désenregistrer tous les hooks

Les abonnés newsletter dans la table emailsubscription NE SONT PAS supprimés lors de la désinstallation, car ils sont partagés avec le module ps_emailsubscription.

---

## 19. Support

Pour le support, les rapports de bugs ou les demandes de fonctionnalités, contactez-nous via le système de messagerie PrestaShop Addons sur la page produit du module.

Lors du signalement d'un problème, veuillez inclure :

- Version de PrestaShop
- Version de PHP
- Nom et version du thème
- Liste des autres modules installés
- Sortie de la console des outils de développement du navigateur (si applicable)
- Étapes pour reproduire le problème
