<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Artisaninweb\SoapWrapper\SoapWrapper;

class SoapClientController extends Controller
{
    protected $soapWrapper;

    public function __construct(SoapWrapper $soapWrapper)
    {
        $this->soapWrapper = $soapWrapper;
    }

    /**
     * Récupérer tous les clients
     */
    public function getAllClients()
    {
        try {
            $clients = Client::all();

            return response()->json([
                'success' => true,
                'data' => $clients,
                'count' => $clients->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un client par ID
     */
    public function getClientById($id)
    {
        try {
            $client = Client::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }
    }

    /**
     * Créer un nouveau client
     */
    public function createClient(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|email|unique:clients,email',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string',
                'ville' => 'nullable|string|max:100',
                'code_postal' => 'nullable|string|max:10',
                'pays' => 'nullable|string|max:100',
            ]);

            $client = Client::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Client créé avec succès',
                'data' => $client
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mettre à jour un client
     */
    public function updateClient(Request $request, $id)
    {
        try {
            $client = Client::findOrFail($id);

            $validated = $request->validate([
                'nom' => 'sometimes|string|max:255',
                'prenom' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:clients,email,' . $id,
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string',
                'ville' => 'nullable|string|max:100',
                'code_postal' => 'nullable|string|max:10',
                'pays' => 'nullable|string|max:100',
                'statut' => 'sometimes|in:actif,inactif'
            ]);

            $client->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Client mis à jour avec succès',
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Supprimer un client
     */
    public function deleteClient($id)
    {
        try {
            $client = Client::findOrFail($id);
            $client->delete();

            return response()->json([
                'success' => true,
                'message' => 'Client supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer le client'
            ], 400);
        }
    }
}
