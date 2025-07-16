import { startStimulusApp } from '@symfony/stimulus-bridge';

const app = startStimulusApp();

// Import and register our custom controllers
import ChartSimpleController from './controllers/chart_simple_controller.js';
app.register('chart-simple', ChartSimpleController);
