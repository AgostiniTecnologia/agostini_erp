# Google Maps

As páginas usam `filament-google-maps.keys.web_key`; geocodificação e rotas usam
`filament-google-maps.keys.server_key`. As duas configurações aceitam
`GOOGLE_MAPS_API_KEY` como alternativa quando a chave específica está vazia.

- `FILAMENT_GOOGLE_MAPS_WEB_API_KEY`: chave do navegador, restrita aos domínios da aplicação.
- `FILAMENT_GOOGLE_MAPS_SERVER_API_KEY`: chave do servidor, restrita ao IP público de saída.
- `GOOGLE_MAPS_API_MAP_ID`: Map ID JavaScript para os marcadores avançados de visitas.

No projeto Google Cloud associado às chaves, conferir faturamento, cotas e ativação de:

- Maps JavaScript API (mapas no navegador).
- Geocoding API (endereços de clientes e empresas).
- Distance Matrix API (Legacy) (sequência das entregas).
- Places API compatível com o pacote Filament Google Maps, se usar sua busca de endereços.

Os registros de agosto/setembro de 2026 mostram `REQUEST_DENIED`, inclusive por API
não ativada. Alterar PHP ou JavaScript não ativa serviços no Google Cloud.
Essa etapa exige acesso administrativo ao projeto. Se o projeto não tiver acesso
à Distance Matrix Legacy, será necessária migração para Routes API; ativar Routes
não habilita o endpoint Legacy usado aqui.

Depois de configurar o ambiente de implantação, executar `php artisan config:cache`.
Não colocar chaves em arquivos versionados nem usar a chave restrita por domínio
nas chamadas do servidor.

O cálculo de rotas consulta lotes de até 25 destinos, compara todos os lotes antes
de escolher a próxima parada e só grava a sequência quando todas as paradas foram
resolvidas. Falhas de rede, coordenadas ausentes e recusas preservam a sequência anterior.

Validação local: `php vendor/bin/phpunit --testsuite=Unit`.
Validar também no navegador: cadastro/edição após consulta de CEP/CNPJ, arraste do
marcador da empresa, clique nos marcadores de visitas, alternância mapa/lista,
navegação Livewire e registro de ponto com permissão de localização.

Referências:
- https://developers.google.com/maps/documentation/distance-matrix/usage-and-billing
- https://developers.google.com/maps/documentation/javascript/advanced-markers/start
- https://developers.google.com/maps/documentation/geocoding/get-api-key
