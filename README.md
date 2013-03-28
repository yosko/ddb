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
Si vous mettez à jour depuis une version antérieure à la v1.3, procédez comme suit :

1. Faites une sauvegarde et exportez tous les rêves au format CSV via l'interface d'administration *.
2. Effectuez une nouvelle installation complète (voir chapitre précédent).
3. Une fois l'installation, connectez-vous.
4. Importez le fichier CSV (sans cocher la case "Remplacer la base", puisqu'elle est déjà vide)

\* **Note :**  si le lien d'export ne fonctionne pas, cela peut être dû à un bug des versions antérieures de DDb. Remplacez dans l'URL la mention ```file.php``` par ```list.php```.

## Licence

DDb est un outil réalisé par Yosko (avec l'aide de @BoboTig). Tous droits réservés.

DDb est distrubué sous licence [GNU LGPL](http://www.gnu.org/licenses/lgpl.html).

DDb utilise aussi le travail d'autres personnes :
* [YosLogin](https://github.com/yosko/yoslogin), par Yosko, aussi sous licence LGPL
* [RainTPL](http://www.raintpl.com/)
* Toutes les images/icônes fournies avec DDb sont la propriété de leurs auteurs respectifs. La plupart des icônes sont de [Yusuke Kamiyamane](http://p.yusukekamiyamane.com/), à l'exception du logo de DDb, réalisé par mes soins.

## Changelog et autres infos

Retrouvez ces informations sur mon site : [DDb sur yosko.net](http://dev.yosko.net/wiki/doku.php?id=web:php:ddb)