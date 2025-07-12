import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Import Chart.js and make it available globally
import Chart from 'chart.js';
window.Chart = Chart;

// Import and register Symfony UX Chartjs controller
import '@symfony/ux-chartjs';

// Import and register our custom controllers
import ChartSimpleController from './controllers/chart_simple_controller.js';
app.register('chart-simple', ChartSimpleController);
