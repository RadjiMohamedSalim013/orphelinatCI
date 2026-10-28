# TODO

- [x] Mettre à jour `templates/admin/orphelinats/create.html.twig` pour reprendre le design stylisé de la page publique `templates/public/proposition/new.html.twig`.
  - [x] Remplacer le bloc `<style>` et la structure HTML actuelle (page admin création).

  - [x] Conserver la logique Symfony/Twig : `form_theme`, `form_start/form_end`, `include('partials/_flashes.html.twig')`.
  - [x] Conserver les champs admin existants (nom/telephone/email/adresse, localisation, fichiers, etc.).
  - [x] Conserver la navigation admin via `admin/layout.html.twig` (ne pas toucher au layout).

- [ ] Tester visuellement la page dans l’interface admin (chargement de la page de création d’orphelinat).


