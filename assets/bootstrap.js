import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Import and register our custom controllers
import ChartCustomController from './controllers/chart_custom_controller.js';
app.register('chart-custom', ChartCustomController);
