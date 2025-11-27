<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run()
    {
        $clients = [
            [
                'nom' => 'Ouédraogo',
                'prenom' => 'Amadou',
                'email' => 'amadou.ouedraogo@example.bf',
                'telephone' => '+226 70 12 34 56',
                'adresse' => 'Secteur 15, Avenue Kwame Nkrumah',
                'ville' => 'Ouagadougou',
                'code_postal' => '01 BP 1234',
                'pays' => 'Burkina Faso',
            ],
            [
                'nom' => 'Sawadogo',
                'prenom' => 'Fatimata',
                'email' => 'fatimata.sawadogo@example.bf',
                'telephone' => '+226 75 98 76 54',
                'adresse' => 'Secteur 30, Rue de la Révolution',
                'ville' => 'Ouagadougou',
                'code_postal' => '01 BP 5678',
                'pays' => 'Burkina Faso',
            ],
            [
                'nom' => 'Compaoré',
                'prenom' => 'Ibrahim',
                'email' => 'ibrahim.compaore@example.bf',
                'telephone' => '+226 71 23 45 67',
                'adresse' => 'Secteur 7, Boulevard de la Liberté',
                'ville' => 'Bobo-Dioulasso',
                'code_postal' => '01 BP 9012',
                'pays' => 'Burkina Faso',
            ],
        ];

        foreach ($clients as $client) {
            Client::create($client);
        }
    }
}
