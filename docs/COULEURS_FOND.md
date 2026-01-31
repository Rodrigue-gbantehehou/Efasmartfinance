# Gestion Centralisée des Couleurs de Fond

## Vue d'ensemble

La couleur de fond de l'application est maintenant gérée de manière centralisée via des variables CSS personnalisées (CSS Custom Properties). Cela permet de modifier la couleur de fond de **toutes les pages** en un seul endroit.

## Comment modifier la couleur de fond

### Étape 1: Localiser les variables CSS

Les variables CSS sont définies dans les fichiers de base suivants :

- `templates/base.html.twig` (pour les pages publiques)
- `templates/dashboard/base.html.twig` (pour le tableau de bord utilisateur)
- `templates/admin/base.html.twig` (pour l'administration)

### Étape 2: Modifier la variable `--app-bg-primary`

Recherchez le bloc suivant dans chaque fichier :

```css
:root {
    --app-bg-primary: #F0F9F1; /* bg-green-50 equivalent - MODIFIER ICI POUR CHANGER LE FOND */
    --app-bg-secondary: #FFFFFF;
    --app-bg-tertiary: #DDEFE0; /* bg-green-100 equivalent */
}
```

Modifiez la valeur de `--app-bg-primary` avec la couleur hexadécimale de votre choix.

### Exemples de couleurs

```css
/* Vert très clair (actuel) */
--app-bg-primary: #F0F9F1;

/* Vert plus prononcé */
--app-bg-primary: #E1F2EB;

/* Blanc pur */
--app-bg-primary: #FFFFFF;

/* Gris très clair */
--app-bg-primary: #F9FAFB;

/* Beige clair */
--app-bg-primary: #FFF8F0;
```

## Variables disponibles

| Variable | Usage | Valeur par défaut |
|----------|-------|-------------------|
| `--app-bg-primary` | Fond principal de toutes les pages | `#F0F9F1` (vert clair) |
| `--app-bg-secondary` | Fond secondaire (cartes, modales) | `#FFFFFF` (blanc) |
| `--app-bg-tertiary` | Fond tertiaire (sections alternées) | `#DDEFE0` (vert moyen) |

## Avantages de cette approche

1. **Centralisation** : Une seule modification affecte toutes les pages
2. **Cohérence** : Garantit une apparence uniforme sur tout le site
3. **Maintenabilité** : Facile à mettre à jour sans chercher dans tous les fichiers
4. **Performance** : Les variables CSS sont natives et très performantes

## Notes importantes

- Les modifications prennent effet immédiatement après rechargement de la page
- Assurez-vous de modifier la variable dans **les trois fichiers base** pour une cohérence totale
- Les couleurs doivent être au format hexadécimal (#RRGGBB) ou RGB/RGBA

## Dépannage

Si la couleur ne change pas :

1. Vérifiez que vous avez modifié les trois fichiers base
2. Videz le cache du navigateur (Ctrl+F5)
3. Vérifiez qu'il n'y a pas de faute de frappe dans le code hexadécimal
4. Assurez-vous que le format est correct (#RRGGBB)
