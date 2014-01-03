DDb
=====

DDb est un outil qui vous permettra de garder trace de vos rêves et ceux de votre entourage. En effet, le meilleur moyen de se souvenir de ses rêves, c'est de les raconter, de les écrire. Prenez l'habitude de les noter dans DDb au réveil.

![Aperçu de DDb](http://dev.yosko.net/wiki/lib/exe/fetch.php?media=web:php:ddb1.3-screen02.jpg "Aperçu de DDb")

Fonctionnalités principales :
* Accès privé multi-utilisateurs
* Chaque utilisateur peut saisir les rêves de différents rêveurs
* Système de tags pour trier les rêves
* Recherche par texte, rêveur ou tag
* Import/Export au format CSV
* Flux RSS accessible aux personnes ayant le lien
* Adaptation du thème pour : affichage de nuit, affichage sur mobile

## Configuration requise

DDb nécessite :
* **PHP 5.3** ( **PHP 5.3.7** ou supérieure recommandée ).
* Modules PHP : pdo et pdo_sqlite

## Installation
1. Placez les sources de DDb sur un répertoire de votre serveur (ex: ```/srv/www/ddb/```)
2. Chargez l'url correspondante dans votre navigateur (ex: ```www.example.com/ddb/```)
3. Vous serez dirigé vers la page d'installation. Assurez-vous qu'aucun problème n'est indiqué et suivez les instruction.
![Impression écran installation](http://dev.yosko.net/wiki/lib/exe/fetch.php?media=web:php:ddb1.3-screen01.jpg "Impression écran installation")
4. Connectez-vous et saisissez votre premier rêve.

## Mise à jour
Si vous mettez à jour depuis une autre version (particulièrement si elle est antérieure à la v1.3), procédez comme suit :

1. Faites une sauvegarde et exportez tous les rêves au format CSV via l'interface d'administration *.
2. Assurez-vous de bien vous déconnecter de DDb
3. Effectuez une nouvelle installation complète (voir chapitre précédent).
4. Une fois l'installation, connectez-vous.
5. Importez le fichier CSV (sans cocher la case "Remplacer la base", puisqu'elle est déjà vide)

\* **Note :**  si le lien d'export ne fonctionne pas, cela peut être dû à un bug des versions antérieures de DDb. Remplacez dans l'URL la mention ```file.php``` par ```list.php```.

## Licence

DDb est un outil réalisé par Yosko (avec l'aide de @BoboTig). Tous droits réservés.

DDb est distrubué sous licence [GNU LGPL](http://www.gnu.org/licenses/lgpl.html).

DDb utilise aussi le travail d'autres personnes :
* [YosLogin](https://github.com/yosko/yoslogin), par Yosko, aussi sous licence LGPL
* [PHP Github Updater](https://github.com/yosko/php-github-updater), par Yosko, aussi sous licence LGPL
* [RainTPL](http://www.raintpl.com/)
* Toutes les images/icônes fournies avec DDb sont la propriété de leurs auteurs respectifs. La plupart des icônes sont de [Yusuke Kamiyamane](http://p.yusukekamiyamane.com/), à l'exception du logo de DDb, réalisé par mes soins.

## Changelog et autres infos

Retrouvez ces informations sur mon site : [DDb sur yosko.net](http://dev.yosko.net/wiki/doku.php?id=web:php:ddb)

* version 1.5 ([zip](https://github.com/yosko/ddb/archive/v1.5.zip), [tar.gz](https://github.com/yosko/ddb/archive/v1.5.tar.gz))
  * Modification possible des commentaires
  * clarification de la notion brouillon/publié : case à cocher "Publier" remplacée par des boutons de sauvegarde différents
  * Refonte de la page de statistiques
  * Les champs non utilisés sont masqués du flux RSS
  * Correction : lors de la (re)publication d'un rêve, celui-ci remontera dans le flux RSS
  * Correction : à l'installation, bien créer la table des commentaires
  * Intégration d'un système de mise à jour automatique (à tester quand la 1.6 sera là)
  * Divers autres corrections mineures
* version 1.4 ([zip](https://github.com/yosko/ddb/archive/v1.4.zip), [tar.gz](https://github.com/yosko/ddb/archive/v1.4.tar.gz)) - utiliser ```update-from-1.3-to-1.4.php``` pour mettre à jour la base de données
  * Possibilité de commenter les rêves des autres
  * Possibilité d'enregistrer un rêve “non publié”. Permet de finir son brouillon tranquillement
  * Mise à jour de YosLogin
  * Affichage de l'auteur (utilisateur) pour chaque rêve
  * Possibilité d'ajouter des tags avec icônes supplémentaires
  * Ajout d'un .htaccess pour limiter les accès sur les élément privés (base de données, dossiers de cache)
  * Nuage de tags : taille pondérée en fonction de l'utilisation de chacun
  * Fusion de tags (via l'administration)
  * Formattage “wiki” du texte pour permettre un texte riche (gras, souligné, liens, smiley, etc…)
  * Affichages de statistiques sur la liste des rêveurs
  * Et de nombreuses corrections mineures
* version 1.3
  * Gestion multi-utilisateur
  * Gestion des autorisations pour chaque utilisateur.
  * Intégration de YosLogin pour gérer l'authentification
  * Ajout d'une clé à l'URL du flux RSS (pour éviter que le premier venu puisse accéder à vos rêves)
  * Ajout d'un thème de nuit facultatif, qui sera utilisée aux heures voulues (exemple : de 20h à 7h)
  * Adaptation du thème pour les mobiles
  * Ajout de tags prédéfinis avec icônes (cauchemar, adulte, lucide)
  * Refonte complète de l'administration/configuration de DDb et ajout de nombreuses fonctionnalités mineures
  * Corrections de bugs
* version 1.2
  * Flux RSS : contenu comple des rêves
  * Purge possible des tags et rêveurs inutilisé
  * Ajout d'auto-complétion pour facilité la saisie des tags (DDb fonctionne toujours si Javascript désactivé)
  * Correction : “se souvenir de moi” fonctionne vraiment
* version 1.1 - utiliser ```update-to-1.1.php``` pour mettre à jour la base de données
  * Ajout d'un flux RSS
  * Option de connexion “se souvenir de moi”
  * Corrections mineures
* version 1.0
  * Version d'origine
