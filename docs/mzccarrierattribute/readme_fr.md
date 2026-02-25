# MZC Carrier Attribute — Documentation

## Version 1.0.0

## Auteur : Marcin Zbijowski Consulting

## Compatibilité : PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Table des matières

1. Aperçu
2. Prérequis
3. Installation
4. Comment ça marche
5. Configuration
6. Exemples
7. Comportement du panier
8. Mises à jour des transporteurs
9. Configuration Multi-Boutique
10. Performance
11. Traductions
12. Dépannage
13. Désinstallation
14. Support

---

## 1. Aperçu

MZC Carrier Attribute vous permet de restreindre les transporteurs disponibles lors du paiement en fonction des valeurs d'attributs des produits (ex. tailles, matériaux, poids).

Le module utilise un modèle de liste blanche : pour chaque valeur d'attribut, vous sélectionnez les transporteurs autorisés. Les valeurs d'attributs sans transporteurs sélectionnés n'imposent aucune restriction — tous les transporteurs restent disponibles. Seules les valeurs d'attributs avec des transporteurs explicitement assignés limitent les options d'expédition.

Lorsqu'un client ajoute des produits avec des valeurs d'attributs restreintes au panier, le module supprime automatiquement les transporteurs non autorisés des options de livraison lors du paiement. Si plusieurs valeurs d'attributs restreintes sont dans le panier, seuls les transporteurs autorisés par TOUTES seront disponibles.

Les produits simples sans combinaisons ne sont jamais affectés par ce module.

---

## 2. Prérequis

- PrestaShop 8.0.0 ou ultérieur (compatible jusqu'à 9.x)
- PHP 8.1 ou ultérieur
- Au moins un transporteur actif configuré
- Au moins un groupe d'attributs avec des valeurs (ex. Taille : S, M, L, XL)

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
2. Uploadez le dossier mzccarrierattribute dans le répertoire modules de votre PrestaShop via FTP
3. Accédez à Modules > Gestionnaire de modules dans le back-office
4. Recherchez MZC Carrier Attribute
5. Cliquez sur Installer, puis Configurer

Après l'installation, un lien vers le module apparaît également dans le menu gauche du back-office sous **Transport** > **Attributs transporteurs**, offrant un accès rapide à la page de configuration.

---

## 4. Comment ça marche

### Le modèle de liste blanche

Le module fonctionne sur un principe simple de liste blanche :

1. Chaque valeur d'attribut produit (ex. « XXL » dans le groupe « Taille ») peut se voir assigner un ou plusieurs transporteurs autorisés
2. Si une valeur d'attribut n'a pas de transporteurs assignés, elle n'impose aucune restriction — tous les transporteurs restent disponibles
3. Si une valeur d'attribut a des transporteurs assignés, seuls ces transporteurs sont autorisés lorsqu'un produit avec cet attribut est dans le panier
4. Lorsque plusieurs valeurs d'attributs restreintes sont dans le panier, le module calcule l'intersection — seuls les transporteurs présents dans TOUTES les listes d'autorisations sont disponibles

### Exemple de logique

Considérons ces règles :

- Taille « XXL » → transporteurs autorisés : Transporteur A, Transporteur B
- Taille « S » → pas de transporteurs assignés (pas de restriction)
- Matériau « Verre » → transporteurs autorisés : Transporteur B, Transporteur C

Scénarios :

- Le panier contient un produit en taille S → tous les transporteurs disponibles (pas de restriction)
- Le panier contient un produit en taille XXL → seuls Transporteur A et B disponibles
- Le panier contient un produit en taille XXL et matériau Verre → seul Transporteur B disponible (intersection de {A, B} et {B, C})
- Le panier contient un produit en taille XXL et un autre en taille S → seuls Transporteur A et B disponibles (seul XXL impose des restrictions)

### Hooks utilisés

Le module utilise trois hooks PrestaShop :

- **actionFilterDeliveryOptionList** — le hook de filtrage principal. Supprime les transporteurs non autorisés des options de livraison présentées lors du paiement.
- **actionCarrierUpdate** — invalide le cache interne lorsqu'un transporteur est modifié dans le back-office, garantissant que les règles font toujours référence aux données actuelles du transporteur.
- **actionPresentCart** — annote le libellé du sous-total d'expédition dans le récapitulatif du panier lorsque des restrictions sont actives, afin que le client voie un avis indiquant que certaines méthodes d'expédition sont restreintes par les attributs des produits.

---

## 5. Configuration

Naviguez vers Modules > Gestionnaire de modules, trouvez MZC Carrier Attribute et cliquez sur Configurer. Vous pouvez également utiliser le raccourci **Transport** > **Attributs transporteurs** dans le menu de gauche.

### Panneau des règles

La page de configuration affiche tous les groupes d'attributs et leurs valeurs dans des panneaux repliables. Pour chaque valeur d'attribut, une rangée de cases à cocher liste tous les transporteurs actifs. Cochez les transporteurs que vous souhaitez autoriser pour cette valeur d'attribut.

### Filtrage

Utilisez le bouton Afficher uniquement les attributs configurés pour filtrer l'affichage aux seules valeurs d'attributs ayant au moins un transporteur assigné. C'est utile lorsque vous avez de nombreux attributs mais seulement quelques règles.

Cliquez sur Afficher tous les attributs pour revenir à la vue complète.

### Enregistrement

Cliquez sur Enregistrer les règles en bas de la page pour sauvegarder toutes les règles en une fois. Le module enregistre toutes les règles dans une seule transaction pour la cohérence.

Un message de succès confirme l'enregistrement. Si aucun attribut n'est trouvé, un message d'erreur est affiché.

---

## 6. Exemples

### Cas d'utilisation 1 : Articles surdimensionnés

Vous vendez des meubles et certains articles ne sont expédiés que par des transporteurs de fret.

1. Créez un groupe d'attributs « Classe d'expédition » avec des valeurs comme « Standard » et « Fret uniquement »
2. Assignez « Fret uniquement » → vos transporteurs de fret
3. Laissez « Standard » sans transporteurs sélectionnés (tous autorisés)
4. Appliquez « Fret uniquement » aux combinaisons de produits surdimensionnés

Résultat : Les produits surdimensionnés n'affichent que les transporteurs de fret lors du paiement.

### Cas d'utilisation 2 : Restrictions régionales

Vous vendez des produits qui ne peuvent être expédiés que par certains transporteurs régionaux en raison de restrictions matérielles.

1. Utilisez un attribut existant comme « Matériau » avec des valeurs telles que « Batterie au lithium », « Inflammable », « Standard »
2. Assignez « Batterie au lithium » → transporteurs certifiés pour les matières dangereuses
3. Assignez « Inflammable » → transporteurs certifiés pour les marchandises inflammables
4. Laissez « Standard » sans transporteurs sélectionnés

Résultat : Les produits avec des matériaux restreints n'affichent que les transporteurs certifiés.

### Cas d'utilisation 3 : Expédition selon la taille

Vous vendez des vêtements et les tailles XXL nécessitent un transporteur spécifique avec un emballage plus grand.

1. Dans le groupe d'attributs « Taille », assignez « XXL » et « XXXL » → votre transporteur gros colis
2. Laissez toutes les autres tailles sans transporteurs sélectionnés

Résultat : Les commandes contenant des articles XXL ou XXXL n'affichent que le transporteur gros colis.

---

## 7. Comportement du panier

### Plusieurs produits

Lorsque le panier contient plusieurs produits avec des attributs restreints, le module calcule l'intersection de tous les ensembles de transporteurs autorisés. Cela signifie :

- Si le Produit A autorise les transporteurs {1, 2, 3} et le Produit B autorise {2, 3, 4}, seuls les transporteurs {2, 3} sont disponibles
- Si l'intersection est vide (aucun transporteur n'est autorisé par tous les produits restreints), toutes les options de livraison sont supprimées et le client ne peut pas procéder au paiement

### Notice dans le sous-total d'expédition

Lorsque des restrictions de transporteurs sont actives pour le panier en cours, le module ajoute le texte « (certaines méthodes restreintes par les attributs des produits) » au libellé du sous-total d'expédition dans le récapitulatif du panier. Cela offre de la transparence au client sur la raison pour laquelle certaines méthodes d'expédition peuvent ne pas être disponibles.

### Panier mixte

Si le panier contient à la fois des produits restreints et non restreints, seules les règles des produits restreints s'appliquent. Les produits non restreints (produits simples ou ceux avec des attributs non configurés) ne limitent pas la disponibilité des transporteurs.

---

## 8. Mises à jour des transporteurs

Lorsque vous modifiez un transporteur dans le back-office PrestaShop (renommage, changement de zones, mise à jour des tarifs), le module invalide automatiquement son cache interne lors du prochain chargement de page. Cela garantit que les règles font toujours référence aux données actuelles du transporteur.

PrestaShop crée un nouveau enregistrement de transporteur avec un nouvel ID lorsque vous éditez un transporteur, mais conserve l'ID de référence du transporteur. Le module utilise les ID de référence, donc les règles restent valides même après modification du transporteur.

Si vous supprimez entièrement un transporteur, ses règles deviennent inactives mais ne sont pas automatiquement supprimées de la configuration. Le module gère gracieusement les références aux transporteurs supprimés en les ignorant lors du filtrage.

---

## 9. Configuration Multi-Boutique

Le module supporte la fonctionnalité multi-boutique de PrestaShop. Chaque boutique peut avoir ses propres règles transporteur-attribut configurées indépendamment.

Les règles sont stockées par boutique dans la table Configuration, donc basculer entre les contextes de boutique dans le back-office affiche et enregistre les règles pour la boutique sélectionnée.

---

## 10. Performance

### Stratégie de cache

Le module utilise une stratégie de cache à deux niveaux :

1. **Cache de configuration** — les règles sont sérialisées en JSON dans la table ps_configuration pour des lectures rapides. Cela évite d'interroger la table des règles à chaque chargement de page.
2. **Cache mémoire par requête** — une fois chargées, les règles et les correspondances de références de transporteurs sont mises en cache dans des propriétés statiques pour la durée de la requête.

Le cache est automatiquement invalidé lorsque :

- Les règles sont enregistrées via la page de configuration
- Un transporteur est mis à jour dans le back-office

### Base de données

Le module crée une seule table (mzc_carrier_attr_rule) avec des colonnes indexées pour des recherches efficaces. La table utilise une clé unique composite pour empêcher les règles en double.

---

## 11. Traductions

Le module est livré avec des traductions complètes pour 5 langues :

- Anglais (en)
- Polonais (pl)
- Français (fr)
- Espagnol (es)
- Italien (it)

Chaque fichier de traduction couvre les 20 chaînes traduisibles de la classe du module, du template de formulaire de règles et des templates d'avertissement.

### Ajouter ou modifier des traductions

Pour traduire le module dans d'autres langues ou modifier les traductions existantes :

1. Accédez à International > Traductions dans votre back-office
2. Sélectionnez Traductions des modules installés dans le menu déroulant Type
3. Sélectionnez la langue cible
4. Trouvez MZC Carrier Attribute dans la liste des modules
5. Entrez vos traductions et cliquez sur Enregistrer

PrestaShop enregistre automatiquement le fichier de traduction dans modules/mzccarrierattribute/translations/.

---

## 12. Dépannage

### Les restrictions de transporteurs ne s'appliquent pas

- Vérifiez que les règles sont enregistrées : accédez à la configuration du module et vérifiez que les bons transporteurs sont cochés pour les valeurs d'attributs concernées
- Assurez-vous que le transporteur est actif et non supprimé
- Videz le cache PrestaShop (Paramètres avancés > Performance > Vider le cache)
- Vérifiez que le produit a une combinaison avec la valeur d'attribut restreinte (les produits simples ne sont pas affectés)

### Tous les transporteurs disparaissent lors du paiement

- Cela se produit lorsque l'intersection de toutes les valeurs d'attributs restreintes est vide (aucun transporteur n'est autorisé par tous les produits restreints dans le panier)
- Passez en revue vos règles pour vous assurer qu'au moins un transporteur est commun à toutes les valeurs d'attributs restreintes susceptibles d'être simultanément dans le panier
- Utilisez le filtre « Afficher uniquement les attributs configurés » pour examiner toutes les règles actives

### Les règles ne s'affichent pas sur la page de configuration

- Assurez-vous d'avoir au moins un groupe d'attributs avec des valeurs
- Assurez-vous d'avoir au moins un transporteur actif
- Si l'un des deux manque, le module affiche un avertissement au lieu du formulaire de règles

### Page de configuration lente

- Si vous avez des centaines de valeurs d'attributs, la page de configuration peut se charger lentement en raison du formulaire volumineux
- Utilisez le filtre « Afficher uniquement les attributs configurés » pour réduire les données affichées
- Envisagez de créer un groupe d'attributs dédié « Classe d'expédition » avec moins de valeurs au lieu de configurer des règles pour chaque attribut

---

## 13. Désinstallation

1. Accédez à Modules > Gestionnaire de modules
2. Trouvez MZC Carrier Attribute
3. Cliquez sur la flèche déroulante et sélectionnez Désinstaller
4. Confirmez la désinstallation

Le module va :

- Supprimer la table des règles (mzc_carrier_attr_rule)
- Supprimer la valeur de configuration (MZC_CARRIER_ATTR_RULES)
- Désenregistrer tous les hooks

Toutes les règles transporteur-attribut seront définitivement supprimées.

---

## 14. Support

Pour le support, les signalements de bugs ou les demandes de fonctionnalités, contactez-nous via le système de messagerie PrestaShop Addons sur la page produit du module.

Lors du signalement d'un problème, veuillez inclure :

- Version de PrestaShop
- Version de PHP
- Nom et version du thème
- Liste des transporteurs actifs et des groupes d'attributs
- Étapes pour reproduire le problème
