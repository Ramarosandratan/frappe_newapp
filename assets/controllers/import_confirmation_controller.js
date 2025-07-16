import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["confirmButton", "cancelButton", "form"]

    connect() {
        console.log("Import confirmation controller connected")
    }

    confirm(event) {
        // Empêche la soumission multiple
        if (this.confirmButtonTarget.disabled) {
            event.preventDefault()
            return
        }

        // Désactive les boutons
        this.confirmButtonTarget.disabled = true
        this.cancelButtonTarget.disabled = true

        // Change le texte du bouton
        const originalText = this.confirmButtonTarget.innerHTML
        this.confirmButtonTarget.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i> Importation en cours...
            <br><small>Veuillez patienter</small>
        `

        // Ajoute une classe de chargement
        this.confirmButtonTarget.classList.add('btn-loading')

        // Affiche un message d'information
        this.showLoadingMessage()

        // Soumet le formulaire après un court délai pour permettre l'affichage
        setTimeout(() => {
            this.formTarget.submit()
        }, 100)
    }

    cancel(event) {
        // Confirmation avant annulation si des données ont été analysées
        if (!confirm("Êtes-vous sûr de vouloir annuler ? L'analyse des fichiers sera perdue.")) {
            event.preventDefault()
        }
    }

    showLoadingMessage() {
        // Crée un message de chargement
        const loadingDiv = document.createElement('div')
        loadingDiv.className = 'alert alert-info mt-3'
        loadingDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-3" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <div>
                    <strong>Importation en cours...</strong><br>
                    <small>Cette opération peut prendre plusieurs minutes. Ne fermez pas cette page.</small>
                </div>
            </div>
        `

        // Insère le message après les boutons
        this.formTarget.parentNode.appendChild(loadingDiv)
    }
}