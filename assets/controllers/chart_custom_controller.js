import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['canvas'];

    connect() {
        console.log('Chart custom controller connected');
        this.setupChartObserver();
    }

    disconnect() {
        console.log('Chart custom controller disconnected');
        this.cleanup();
    }

    cleanup() {
        // Nettoyer les event listeners
        const chartTypeSelect = document.getElementById('chartType');
        if (chartTypeSelect && this.handleChartTypeChange) {
            chartTypeSelect.removeEventListener('change', this.handleChartTypeChange);
        }

        const checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
        checkboxes.forEach((checkbox) => {
            if (checkbox._chartHandler) {
                checkbox.removeEventListener('change', checkbox._chartHandler);
                delete checkbox._chartHandler;
            }
        });
    }

    setupChartObserver() {
        // Observer pour détecter quand le canvas est ajouté au DOM
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        const canvas = node.tagName === 'CANVAS' ? node : node.querySelector('canvas');
                        if (canvas) {
                            console.log('Canvas detected, waiting for chart...');
                            this.waitForChart(canvas);
                        }
                    }
                });
            });
        });

        observer.observe(this.element, { childList: true, subtree: true });

        // Vérifier aussi si le canvas existe déjà
        const existingCanvas = this.element.querySelector('canvas');
        if (existingCanvas) {
            console.log('Existing canvas found, waiting for chart...');
            this.waitForChart(existingCanvas);
        }
    }

    waitForChart(canvas) {
        let attempts = 0;
        const maxAttempts = 50;

        const checkChart = () => {
            attempts++;
            console.log(`Attempt ${attempts}: Checking for chart on canvas`);

            if (window.Chart) {
                // Essayer différentes méthodes pour obtenir le chart
                let chart = canvas.chart || 
                           canvas._chart || 
                           (window.Chart.getChart && window.Chart.getChart(canvas));

                if (chart) {
                    console.log('Chart found!', chart);
                    this.chart = chart;
                    this.setupCustomFeatures();
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

    setupCustomFeatures() {
        console.log('Setting up custom features...');
        this.setupCustomOptions();
        this.initializeCheckboxes();
        this.setupEventListeners();
    }

    setupCustomOptions() {
        if (!this.chart) return;

        console.log('Applying custom options to chart');

        try {
            // Configuration personnalisée pour les tooltips
            if (this.chart.options?.plugins?.tooltip) {
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

            // Configuration personnalisée pour les ticks de l'axe Y
            if (this.chart.options?.scales?.y?.ticks) {
                this.chart.options.scales.y.ticks.callback = function(value) {
                    return new Intl.NumberFormat('fr-FR').format(value) + ' Ar';
                };
            }

            this.chart.update();
            console.log('Custom options applied successfully');
        } catch (error) {
            console.error('Error applying custom options:', error);
        }
    }

    initializeCheckboxes() {
        if (!this.chart) return;

        console.log('Initializing checkboxes based on chart datasets...');
        console.log('Chart datasets:', this.chart.data.datasets.map(d => ({ label: d.label, hidden: d.hidden })));

        const checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
        
        checkboxes.forEach((checkbox) => {
            const componentName = checkbox.value;
            
            // Trouver le dataset correspondant
            const datasetIndex = this.chart.data.datasets.findIndex(dataset => 
                dataset.label === componentName
            );

            if (datasetIndex !== -1) {
                // Vérifier si le dataset est visible
                const isVisible = this.chart.isDatasetVisible(datasetIndex);
                checkbox.checked = isVisible;
                console.log(`Checkbox for "${componentName}" set to:`, isVisible);
            } else {
                // Si le dataset n'existe pas, décocher la checkbox
                checkbox.checked = false;
                console.log(`Dataset not found for "${componentName}", checkbox unchecked`);
            }
        });
    }

    setupEventListeners() {
        console.log('Setting up event listeners...');

        // Écouter les changements de type de graphique
        const chartTypeSelect = document.getElementById('chartType');
        if (chartTypeSelect) {
            console.log('Adding chart type listener');
            // Supprimer les anciens listeners pour éviter les doublons
            chartTypeSelect.removeEventListener('change', this.handleChartTypeChange);
            this.handleChartTypeChange = (event) => {
                console.log('Chart type change event triggered:', event.target.value);
                this.changeChartType(event.target.value);
            };
            chartTypeSelect.addEventListener('change', this.handleChartTypeChange);
        } else {
            console.warn('Chart type select not found');
        }

        // Écouter les changements de visibilité des composants
        const checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
        console.log('Found', checkboxes.length, 'component checkboxes');

        checkboxes.forEach((checkbox, index) => {
            console.log(`Setting up listener for checkbox ${index}: ${checkbox.value}`);
            
            // Supprimer les anciens listeners pour éviter les doublons
            const oldHandler = checkbox._chartHandler;
            if (oldHandler) {
                checkbox.removeEventListener('change', oldHandler);
            }
            
            // Créer un nouveau handler
            const newHandler = (event) => {
                console.log('Checkbox change event triggered:', event.target.value, 'checked:', event.target.checked);
                this.toggleDatasetVisibility(event.target.value, event.target.checked);
            };
            
            checkbox._chartHandler = newHandler;
            checkbox.addEventListener('change', newHandler);
        });
    }

    changeChartType(newType) {
        if (!this.chart) return;

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
        if (!this.chart) return;

        console.log('Toggling dataset:', componentName, 'visible:', isVisible);

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
                console.log('Available datasets:', this.chart.data.datasets.map(d => d.label));
            }
        } catch (error) {
            console.error('Error toggling dataset visibility:', error);
        }
    }
}