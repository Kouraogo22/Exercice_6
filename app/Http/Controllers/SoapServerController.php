<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use SoapServer;
use SoapFault;

class SoapServerController extends Controller
{
    /**
     * Gérer les requêtes SOAP
     */
    public function handle(Request $request)
    {
        // Désactiver le cache WSDL en développement
        ini_set('soap.wsdl_cache_enabled', '0');

        try {
            $wsdlPath = public_path('soap/client.wsdl');

            if (!file_exists($wsdlPath)) {
                return response('WSDL file not found', 404);
            }

            // Créer le serveur SOAP
            $server = new SoapServer($wsdlPath, [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'trace' => 1,
                'exceptions' => true
            ]);

            // Définir la classe qui gère les opérations
            $server->setClass(ClientSoapService::class);

            // Capturer la sortie
            ob_start();
            $server->handle();
            $response = ob_get_clean();

            return response($response, 200)
                ->header('Content-Type', 'text/xml; charset=utf-8');

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Servir le fichier WSDL
     */
    public function wsdl()
    {
        $wsdlPath = public_path('soap/client.wsdl');

        if (!file_exists($wsdlPath)) {
            return response('WSDL file not found', 404);
        }

        $wsdl = file_get_contents($wsdlPath);

        // Remplacer l'URL dans le WSDL par l'URL actuelle
        $wsdl = str_replace(
            'http://localhost/soap/client',
            url('/soap/server'),
            $wsdl
        );

        return response($wsdl, 200)
            ->header('Content-Type', 'text/xml; charset=utf-8');
    }
}

/**
 * Classe de service SOAP
 * Cette classe implémente toutes les opérations définies dans le WSDL
 */
class ClientSoapService
{
    /**
     * Récupérer tous les clients
     */
    public function GetAllClients($request)
    {
        try {
            $clients = Client::all()->map(function($client) {
                return [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'prenom' => $client->prenom,
                    'email' => $client->email,
                    'telephone' => $client->telephone ?? '',
                    'adresse' => $client->adresse ?? '',
                    'ville' => $client->ville ?? '',
                    'code_postal' => $client->code_postal ?? '',
                    'pays' => $client->pays ?? '',
                    'statut' => $client->statut ?? 'actif',
                ];
            })->toArray();

            return [
                'clients' => $clients
            ];

        } catch (\Exception $e) {
            throw new SoapFault('Server', 'Erreur lors de la récupération des clients: ' . $e->getMessage());
        }
    }

    /**
     * Récupérer un client par ID
     */
    public function GetClientById($request)
    {
        try {
            $client = Client::findOrFail($request->id);

            return [
                'client' => [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'prenom' => $client->prenom,
                    'email' => $client->email,
                    'telephone' => $client->telephone ?? '',
                    'adresse' => $client->adresse ?? '',
                    'ville' => $client->ville ?? '',
                    'code_postal' => $client->code_postal ?? '',
                    'pays' => $client->pays ?? '',
                    'statut' => $client->statut ?? 'actif',
                ]
            ];

        } catch (\Exception $e) {
            throw new SoapFault('Server', 'Client non trouvé');
        }
    }

    /**
     * Créer un nouveau client
     */
    public function CreateClient($request)
    {
        try {
            $client = Client::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'telephone' => $request->telephone ?? null,
                'adresse' => $request->adresse ?? null,
                'ville' => $request->ville ?? null,
                'code_postal' => $request->code_postal ?? null,
                'pays' => $request->pays ?? 'Burkina Faso',
                'statut' => 'actif',
            ]);

            return [
                'status' => 'Client créé avec succès. ID: ' . $client->id
            ];

        } catch (\Exception $e) {
            throw new SoapFault('Server', 'Erreur lors de la création du client: ' . $e->getMessage());
        }
    }

    /**
     * Mettre à jour un client
     */
    public function UpdateClient($request)
    {
        try {
            $client = Client::findOrFail($request->id);

            $updateData = [];
            if (isset($request->nom)) $updateData['nom'] = $request->nom;
            if (isset($request->prenom)) $updateData['prenom'] = $request->prenom;
            if (isset($request->email)) $updateData['email'] = $request->email;
            if (isset($request->telephone)) $updateData['telephone'] = $request->telephone;
            if (isset($request->adresse)) $updateData['adresse'] = $request->adresse;
            if (isset($request->ville)) $updateData['ville'] = $request->ville;
            if (isset($request->code_postal)) $updateData['code_postal'] = $request->code_postal;
            if (isset($request->pays)) $updateData['pays'] = $request->pays;
            if (isset($request->statut)) $updateData['statut'] = $request->statut;

            $client->update($updateData);

            return [
                'status' => 'Client mis à jour avec succès'
            ];

        } catch (\Exception $e) {
            throw new SoapFault('Server', 'Erreur lors de la mise à jour du client: ' . $e->getMessage());
        }
    }

    /**
     * Supprimer un client
     */
    public function DeleteClient($request)
    {
        try {
            $client = Client::findOrFail($request->id);
            $client->delete();

            return [
                'status' => 'Client supprimé avec succès'
            ];

        } catch (\Exception $e) {
            throw new SoapFault('Server', 'Erreur lors de la suppression du client: ' . $e->getMessage());
        }
    }
}
