import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('Chart simple controller connected');
        this.chart = null;
        
        // Écouter l'événement Symfony UX Chartjs
        this.element.addEventListener('chartjs:connect', (event) => {
            console.log('Chart.js connect event received', event.detail);
            this.chart = event.detail.chart;
            this.setupFeatures();
        });
        
        // Fallback: essayer de trouver le graphique après un délai
        setTimeout(() => {
            if (!this.chart) {
                this.findExistingChart();
            }
        }, 1000);
    }

    findExistingChart() {
        console.log('Looking for existing chart...');
        const canvas = this.element.querySelector('canvas');
        if (canvas && window.Chart) {
            const chart = window.Chart.getChart(canvas);
            if (chart) {
                console.log('Found existing chart:', chart);
                this.chart = chart;
                this.setupFeatures();
            } else {
                console.log('No chart found on canvas');
            }
        }
    }

    setupFeatures() {
        console.log('Setting up chart features...');
        this.initializeCheckboxes();
        this.setupEventListeners();
    }

    initializeCheckboxes() {
        if (!this.chart) {
            console.log('No chart available for checkbox initialization');
            return;
        }

        console.log('Initializing checkboxes...');
        const checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
        
        checkboxes.forEach((checkbox) => {
            const componentName = checkbox.value;
            const datasetIndex = this.chart.data.datasets.findIndex(dataset => 
                dataset.label === componentName
            );

            if (datasetIndex !== -1) {
                const isVisible = this.chart.isDatasetVisible(datasetIndex);
                checkbox.checked = isVisible;
                console.log(`Checkbox for "${componentName}" set to:`, isVisible);
            } else {
                console.log(`Dataset not found for component: ${componentName}`);
            }
        });
    }

    setupEventListeners() {
        console.log('Setting up event listeners...');

        // Type de graphique
        const chartTypeSelect = document.getElementById('chartType');
        if (chartTypeSelect) {
            chartTypeSelect.addEventListener('change', (event) => {
                this.changeChartType(event.target.value);
            });
        }

        // Checkboxes pour les composants
        document.addEventListener('change', (event) => {
            if (event.target.matches('#componentCheckboxes input[type="checkbox"]')) {
                this.toggleDatasetVisibility(event.target.value, event.target.checked);
            }
        });
    }

    changeChartType(newType) {
        if (!this.chart) {
            console.log('No chart available for type change');
            return;
        }

        console.log('Changing chart type to:', newType);
        try {
            this.chart.config.type = newType;
            this.chart.update();
            console.log('Chart type changed successfully');
        } catch (error) {
            console.error('Error changing chart type:', error);
        }
    }

    toggleDatasetVisibility(componentName, isVisible) {
        if (!this.chart) {
            console.log('No chart available for dataset toggle');
            return;
        }

        console.log('Toggling dataset:', componentName, 'to', isVisible);
        
        try {
            const datasetIndex = this.chart.data.datasets.findIndex(dataset => 
                dataset.label === componentName
            );

            if (datasetIndex !== -1) {
                this.chart.setDatasetVisibility(datasetIndex, isVisible);
                this.chart.update();
                console.log('Dataset visibility toggled successfully');
            } else {
                console.warn('Dataset not found:', componentName);
            }
        } catch (error) {
            console.error('Error toggling dataset:', error);
        }
    }

    disconnect() {
        console.log('Chart simple controller disconnected');
        this.chart = null;
    }
}