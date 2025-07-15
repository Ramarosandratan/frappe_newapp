<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SalaryModifierControllerTest extends WebTestCase
{
    public function testSalaryModifierPageLoads(): void
    {
        $client = static::createClient();
        
        // Test que la page se charge correctement
        $crawler = $client->request('GET', '/salary/modifier');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Modification des éléments de salaire');
        
        // Vérifier que la case à cocher pour les pourcentages mensuels est présente
        $this->assertSelectorExists('input[name="use_monthly_percentages"]');
        $this->assertSelectorExists('#monthly_percentages_section');
        
        // Vérifier que les 12 champs de pourcentages mensuels sont présents
        for ($month = 1; $month <= 12; $month++) {
            $this->assertSelectorExists("input[name=\"monthly_percentages[{$month}]\"]");
        }
        
        // Vérifier que le JavaScript est inclus
        $this->assertSelectorExists('script');
        
        // Vérifier la documentation mise à jour
        $this->assertSelectorTextContains('.alert-info', 'Pourcentages mensuels');
        $this->assertSelectorTextContains('.alert-warning', 'Fonctionnalité des pourcentages mensuels');
    }

    public function testMonthlyPercentagesAjaxEndpoint(): void
    {
        $client = static::createClient();
        
        // Test de l'endpoint AJAX pour récupérer les pourcentages
        $client->request('GET', '/salary/modifier/percentages/Test%20Component');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('percentages', $response);
        $this->assertTrue($response['success']);
    }

    public function testFormSubmissionWithMonthlyPercentages(): void
    {
        $client = static::createClient();
        
        // Simuler une soumission de formulaire avec pourcentages mensuels
        $crawler = $client->request('GET', '/salary/modifier');
        
        // Note: Ce test nécessiterait une configuration complète d'ERPNext
        // Pour l'instant, on vérifie juste que les champs sont présents
        $form = $crawler->selectButton('Appliquer les modifications')->form();
        
        // Vérifier que tous les champs nécessaires sont présents dans le formulaire
        $this->assertTrue($form->has('component'));
        $this->assertTrue($form->has('condition'));
        $this->assertTrue($form->has('condition_value'));
        $this->assertTrue($form->has('new_value'));
        $this->assertTrue($form->has('start_date'));
        $this->assertTrue($form->has('end_date'));
        $this->assertTrue($form->has('use_monthly_percentages'));
        
        // Vérifier les champs de pourcentages mensuels
        for ($month = 1; $month <= 12; $month++) {
            $this->assertTrue($form->has("monthly_percentages[{$month}]"));
        }
    }
}