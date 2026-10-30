import './stimulus_bootstrap.js';
import * as Turbo from '@hotwired/turbo';

// Navigation classique : chaque page charge ses propres scripts (carte, formulaires).
Turbo.session.drive = false;
