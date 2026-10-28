import './stimulus_bootstrap.js';
import * as Turbo from '@hotwired/turbo';
import './styles/app.css';

// Navigation classique : chaque page charge ses propres scripts (carte, formulaires).
Turbo.session.drive = false;
