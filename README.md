# Manuel numériques PDF

Ce dépôt contient trois programmes permettant de télécharger des manuels (ou livres) numériques en fichier PDF depuis la plateforme éducadhoc, depuis la plateforme Calaméo ou depuis l'URL du fichier OPF d'un OEBPS dézippé par exemple pour BiblioManuel.

Les manuels (ou livres) sont susceptibles d'être soumis à des droits d'auteurs et licences, vous êtes l'unique responsable du téléchargement et de la possession des manuels et de ces programmes. L'utilisation de ces programmes n'engagent que vous.

## Utilisation

Ces programmes nécessitent d'avoir php, composer et docker d'installer sur votre machine. Ils utilisent Gotenberg pour la conversion et le traitement en PDF.

### Installation des dépendances

```shell
composer install
```

### Lancement de Gotenberg

```shell
docker run --rm -p 3000:3000 -e MAXIMUM_WAIT_TIMEOUT=240 thecodingmachine/gotenberg:6
```

### Téléchargement d'un PDF

Depuis Calaméo :
```shell
php dlcalameo.php
```

Depuis éducadhoc :
```shell
php dleducadhoc.php
```

Depuis l'URL du fichier OPF d'un OEBPS dézippé :
```shell
php dlfromopf.php
```

Répondez ensuite aux demandes du programme puis attendez la fin du téléchargement des pages et de la fusion des pages. Votre dossier contiendra à la fin un fichier de configuration (dans le cas où il faudrait reprendre le téléchargement), un fichier PDF par page et un fichier PDF contenant toutes les pages.

Vous pourrez utiliser l'outil `pdftk` afin de supprimer les premières pages si elles décalent la pagination. Par exemple si votre PDF est décallé de trois pages :
```shell
pdftk manuel.pdf cat 3-end output manuel_corrige.pdf
```

**Pour plus d'informations sur le logiciel, créez une issue.**

## License (EN)

**ManuelNumeriquePDF**

***Copyright (C) 2020 ComFoxx***

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see https://www.gnu.org/licenses/.
