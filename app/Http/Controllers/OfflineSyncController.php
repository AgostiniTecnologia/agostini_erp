<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OfflineSyncController extends Controller
{
    /**
     * Recebe a fila de sincronização enviada pelo navegador (PWA offline).
     */
    public function sync(Request $request)
    {
        $queue = $request->input('queue', []);
        $results = [];
        $errors = [];

        if (empty($queue)) {
            return response()->json([
                'success' => true,
                'message' => 'Fila de sincronização vazia.',
                'results' => []
            ], 200);
        }

        Log::info('Iniciando sincronização offline', [
            'user_id' => auth()->id(),
            'queue_size' => count($queue)
        ]);

        // Mapeamento: storeName até Model associado
        $modelMap = [
            'clients'       => \App\Models\Client::class,
            'products'      => \App\Models\Product::class,
            'sales_visits'  => \App\Models\SalesVisit::class,
            'sales_orders'  => \App\Models\SalesOrder::class,
            // Adicione aqui os demais módulos offline conforme necessário:
            // 'financial_entries' => \App\Models\FinancialEntry::class,
            // 'pricing_tables'    => \App\Models\PricingTable::class,
        ];

        DB::beginTransaction();

        try {
            foreach ($queue as $index => $op) {
                
                $storeName = $op['storeName'] ?? null;
                $action    = $op['action']    ?? null;
                $payload   = $op['payload']   ?? [];
                $timestamp = $op['timestamp'] ?? null;

                // Validar operaÃ§Ã£o
                if (!$storeName || !$action || !$payload) {
                    $errors[] = [
                        'index'   => $index,
                        'status'  => 'error',
                        'message' => 'Operação inválida ou incompleta.',
                        'item'    => $op
                    ];
                    continue;
                }

                // Verificar se store está mapeado
                if (!array_key_exists($storeName, $modelMap)) {
                    Log::warning("OfflineSyncController: Store '{$storeName}' não mapeado.", ['operation' => $op]);
                    $errors[] = [
                        'index'   => $index,
                        'status'  => 'error',
                        'message' => "Store '{$storeName}' não está mapeado no sistema.",
                        'item'    => $op
                    ];
                    continue;
                }

                $ModelClass = $modelMap[$storeName];

                try {
                    $result = $this->processOperation($ModelClass, $action, $payload, $storeName);
                    
                    $results[] = array_merge($result, [
                        'index'     => $index,
                        'timestamp' => $timestamp,
                        'storeName' => $storeName
                    ]);

                } catch (\Illuminate\Validation\ValidationException $e) {
                    $errors[] = [
                        'index'      => $index,
                        'status'     => 'validation_error',
                        'message'    => 'Erro de validaÃ§Ã£o',
                        'errors'     => $e->errors(),
                        'item'       => $op
                    ];
                    
                    Log::warning('Erro de validação na sincronização', [
                        'operation' => $op,
                        'errors' => $e->errors()
                    ]);

                } catch (\Exception $e) {
                    $errors[] = [
                        'index'   => $index,
                        'status'  => 'error',
                        'message' => $e->getMessage(),
                        'item'    => $op
                    ];
                    
                    Log::error("Erro ao processar item offline", [
                        'erro' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'item' => $op,
                    ]);
                }
            }

            // Commit apenas se não houver erros crí­ticos
            if (count($errors) === 0) {
                DB::commit();
                
                Log::info('Sincronização offline concluída com sucesso', [
                    'user_id' => auth()->id(),
                    'synced_count' => count($results)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Sincronização concluída com sucesso.',
                    'results' => $results,
                    'synced_count' => count($results),
                ], 200);
            } else {
                // Se houver erros, fazer rollback ou commit parcial
                // (depende da sua regra de negócio)
                DB::commit(); // Manter operações bem-sucedidas
                
                Log::warning('Sincronização offline concluída com erros', [
                    'user_id' => auth()->id(),
                    'synced_count' => count($results),
                    'errors_count' => count($errors)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Sincronização concluída com erros.',
                    'results' => $results,
                    'errors' => $errors,
                    'synced_count' => count($results),
                    'errors_count' => count($errors),
                ], 207); // 207 Multi-Status
            }

        } catch (\Exception $fatal) {
            DB::rollBack();
            
            Log::error("Erro fatal na sincronização offline", [
                'user_id' => auth()->id(),
                'error' => $fatal->getMessage(),
                'trace' => $fatal->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro fatal durante a sincronização.',
                'error'   => $fatal->getMessage(),
            ], 500);
        }
    }

    /**
     * Processa uma operação individual (create, update, delete)
     */
    private function processOperation(string $ModelClass, string $action, array $payload, string $storeName): array
    {
        $companyId = auth()->user()->company_id;

        switch ($action) {

            case 'create':
                $localId = $payload['id'] ?? null;
                
                // Remover ID temporário e adicionar company_id
                unset($payload['id']);
                $payload['company_id'] = $companyId;
                
                // Criar registro
                $record = $ModelClass::create($payload);
                
                Log::info("Registro criado via sync offline", [
                    'model' => $ModelClass,
                    'local_id' => $localId,
                    'server_id' => $record->id
                ]);

                return [
                    'status'     => 'success',
                    'action'     => 'create',
                    'local_id'   => $localId,
                    'server_id'  => $record->id,
                    'uuid'       => $record->uuid ?? null,
                ];

            case 'update':
                if (!isset($payload['id']) && !isset($payload['uuid'])) {
                    return [
                        'status'  => 'error',
                        'message' => 'Update sem ID ou UUID.',
                    ];
                }

                // Buscar registro por ID ou UUID
                $record = null;
                if (isset($payload['uuid'])) {
                    $record = $ModelClass::where('uuid', $payload['uuid'])
                        ->where('company_id', $companyId)
                        ->first();
                } elseif (isset($payload['id'])) {
                    $record = $ModelClass::where('id', $payload['id'])
                        ->where('company_id', $companyId)
                        ->first();
                }

                if (!$record) {
                    return [
                        'status'  => 'error',
                        'message' => "Registro não encontrado para update.",
                    ];
                }

                // Remover campos que não devem ser atualizados
                unset($payload['id'], $payload['uuid'], $payload['company_id']);

                $record->update($payload);
                
                Log::info("Registro atualizado via sync offline", [
                    'model' => $ModelClass,
                    'id' => $record->id
                ]);

                return [
                    'status' => 'success',
                    'action' => 'update',
                    'id'     => $record->id,
                    'uuid'   => $record->uuid ?? null,
                ];

            case 'delete':
                if (!isset($payload['id']) && !isset($payload['uuid'])) {
                    return [
                        'status'  => 'error',
                        'message' => 'Delete sem ID ou UUID.',
                    ];
                }

                // Buscar e deletar
                $deleted = false;
                if (isset($payload['uuid'])) {
                    $deleted = $ModelClass::where('uuid', $payload['uuid'])
                        ->where('company_id', $companyId)
                        ->delete();
                } elseif (isset($payload['id'])) {
                    $deleted = $ModelClass::where('id', $payload['id'])
                        ->where('company_id', $companyId)
                        ->delete();
                }

                if (!$deleted) {
                    return [
                        'status'  => 'error',
                        'message' => 'Registro não encontrado para delete.',
                    ];
                }
                
                Log::info("Registro deletado via sync offline", [
                    'model' => $ModelClass,
                    'payload' => $payload
                ]);

                return [
                    'status' => 'success',
                    'action' => 'delete',
                    'id'     => $payload['id'] ?? null,
                    'uuid'   => $payload['uuid'] ?? null,
                ];

            default:
                return [
                    'status'  => 'error',
                    'message' => "Ação desconhecida: {$action}",
                ];
        }
    }
}