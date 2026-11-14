# Site vitrine Civilina — V2

Site statique (HTML + CSS, sans build) qui présente Civilina autour de ses trois axes et de son outil IA :

| Page | Chemin |
|---|---|
| Accueil | `site-vitrine/index.html` |
| CIVILINA Engineering | `site-vitrine/engineering/` |
| CIVILINA Entreprise | `site-vitrine/entreprise/` |
| CIVILINA Digital | `site-vitrine/digital/` |
| CIVILINA IA | `site-vitrine/ia/` |
| Journal (7 articles) | `site-vitrine/journal/` |

Style commun : `site-vitrine/assets/css/site.css`. Plan éditorial interne : [`plan-editorial.md`](plan-editorial.md) (à ne pas mettre en ligne).

## Statut

**Maquette, non publiée.** Chaque page porte `<meta name="robots" content="noindex, nofollow">` : à retirer seulement au moment de la mise en ligne.

## Prévisualiser en local

```bash
php -S 127.0.0.1:8765 -t site-vitrine
```

Puis ouvrir http://127.0.0.1:8765/.

## Mettre en ligne (civilina.com, cPanel)

1. Sauvegarder le `public_html` actuel (hors serveur).
2. Dérouler la checklist « Avant la mise en ligne de la V2 » de [`plan-editorial.md`](plan-editorial.md).
3. Téléverser **uniquement le contenu de `site-vitrine/`** dans `public_html`, sans écraser les sous-domaines ni Mon Profil.
4. Conserver les adresses des articles existants (déjà indexées) : c'est le cas dans la V2.

## Liaison future avec la plateforme CIVILINA

La plateforme (application Laravel de ce dépôt) n'est **pas encore reliée** au site vitrine. À faire quand la plateforme sera prête :

- [ ] Correctifs de sécurité P0 (accès par organisation, API `/api/artifacts`, isolation des tests) **testés** sur une base jetable.
- [ ] Adresse publique de la plateforme décidée (par exemple un sous-domaine dédié).
- [ ] Bouton « Connexion » / « Accès plateforme » ajouté dans l'en-tête du site vitrine.
- [ ] Pages Engineering (base normative) et IA : boutons « Demander l'accès pilote » reliés au formulaire ou à l'inscription de la plateforme.
- [ ] Statuts « Accès pilote / En développement » des pages Engineering et IA mis à jour selon l'état réel.
- [ ] Mention « données cloisonnées » de la page IA confirmée par les tests de sécurité.
