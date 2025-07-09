import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('Custom chartjs controller connected');
        // Attendre que le contrôleur chart de Symfony UX soit initialisé
        this.waitForChart();
    }

    waitForChart() {
        let attempts = 0;
        const maxAttempts = 50; // 5 secondes maximum
        
        const checkChart = () => {
            attempts++;
            const chartElement = this.element.querySelector('canvas');
            
            console.log(`Attempt ${attempts}: Looking for chart...`);
            console.log('Canvas element:', chartElement);
            console.log('Window.Chart:', typeof window.Chart);
            
            if (chartElement && window.Chart) {
                // Essayer différentes façons d'accéder au chart
                let chart = chartElement.chart || 
                           chartElement._chart || 
                           (window.Chart && window.Chart.getChart && window.Chart.getChart(chartElement));
                
                console.log('Found chart:', chart);
                
                if (chart) {
                    this.chart = chart;
                    console.log('Chart connected successfully!');
                    this.setupCustomOptions();
                    this.setupEventListeners();
                    return;
                }
            }
            
            if (attempts < maxAttempts) {
                setTimeout(checkChart, 100);
            } else {
                console.error('Failed to find chart after', maxAttempts, 'attempts');
            }
        };
        checkChart();
    }

    setupCustomOptions() {
        if (!this.chart) {
            console.error('No chart available for setupCustomOptions');
            return;
        }

        console.log('Setting up custom options for chart:', this.chart);

        try {
            // Configuration personnalisée pour les tooltips et les ticks
            if (this.chart.options && this.chart.options.plugins && this.chart.options.plugins.tooltip) {
                this.chart.options.plugins.tooltip.callbacks = {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed.y !== null) {
                            label += new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' Ar';
                        }
                        return label;
                    }
                };
            }

            if (this.chart.options && this.chart.options.scales && this.chart.options.scales.y && this.chart.options.scales.y.ticks) {
                this.chart.options.scales.y.ticks.callback = function(value, index, values) {
                    return new Intl.NumberFormat('fr-FR').format(value) + ' Ar';
                };
            }

            this.chart.update();
            console.log('Custom options applied successfully');
        } catch (error) {
            console.error('Error setting up custom options:', error);
        }
    }

    setupEventListeners() {
        console.log('Setting up event listeners...');
        
        // Écouter les changements de type de graphique
        const chartTypeSelect = document.getElementById('chartType');
        if (chartTypeSelect) {
            console.log('Found chartType select, adding event listener');
            chartTypeSelect.addEventListener('change', (event) => {
                console.log('Chart type changed to:', event.target.value);
                this.changeType(event);
            });
        } else {
            console.warn('chartType select not found');
        }

        // Écouter les changements de visibilité des composants
        const checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
        console.log('Found', checkboxes.length, 'component checkboxes');
        
        checkboxes.forEach((checkbox, index) => {
            console.log(`Adding listener to checkbox ${index}:`, checkbox.value);
            checkbox.addEventListener('change', (event) => {
                console.log('Checkbox changed:', event.target.value, 'checked:', event.target.checked);
                this.toggleDataset(event);
            });
        });
    }

    changeType(event) {
        if (!this.chart) {
            console.error('No chart available for changeType');
            return;
        }

        try {
            console.log('Changing chart type from', this.chart.config.type, 'to', event.target.value);
            this.chart.config.type = event.target.value;
            this.chart.update();
            console.log('Chart type changed successfully');
        } catch (error) {
            console.error('Error changing chart type:', error);
        }
    }

    toggleDataset(event) {
        if (!this.chart) {
            console.error('No chart available for toggleDataset');
            return;
        }

        const componentName = event.target.value;
        const isChecked = event.target.checked;

        console.log('Toggling dataset:', componentName, 'to', isChecked);
        console.log('Available datasets:', this.chart.data.datasets.map(d => d.label));

        try {
            // Trouver le dataset correspondant
            const datasetIndex = this.chart.data.datasets.findIndex(dataset => 
                dataset.label === componentName
            );

            console.log('Found dataset at index:', datasetIndex);

            if (datasetIndex !== -1) {
                this.chart.setDatasetVisibility(datasetIndex, isChecked);
                this.chart.update();
                console.log('Dataset visibility toggled successfully');
            } else {
                console.warn('Dataset not found for component:', componentName);
            }
        } catch (error) {
            console.error('Error toggling dataset:', error);
        }
    }
}