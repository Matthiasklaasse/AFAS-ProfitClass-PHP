<?php

namespace Erdee\Afasprofit;

use iPublications\Profit\Connector;
use Spatie\ArrayToXml\ArrayToXml;
use Exception;
use iPublications\Profit\Connection;
use iPublications\Profit\UpdateConnector;

class DeclarationUploader
{
    protected Connection $connector;
    protected UpdateConnector $updateConnector;
    protected string $connectorId;

    public function __construct(string $connectorId = 'FiEntryPar')
    {
        $hasToBeInEnv[] = ['AFAS_HOST', 'AFAS_TOKEN'];
        $this->connector = new Connection;
        $this->connectorId = $connectorId;

        $this->connector->SetTargetURL($_ENV['AFAS_HOST']);
        $this->connector->SetTimeout(20);

        $this->updateConnector = new UpdateConnector(clone $this->connector);
        $this->updateConnector->SetToken($_ENV['AFAS_CONNECTOR_TOKEN']);
        $this->updateConnector->SetConnector($connectorId);
    }

    public function upload(array $data): mixed
    {
        // Uitleg van AFAS termen en hun standaardwaarden:
        // VaAs: 1 = Grootboek, 2 = Debiteur, 3 = Crediteur [standaard: 3 voor crediteurregel, 1 voor grootboekregel]
        // AcNr: Crediteurnummer [standaard: $baseData->getCreditorId() voor crediteur, 6800 voor grootboek]
        // EnDa: Datum van mutatie [standaard: $bookDate]
        // BpDa: Factuurdatum [standaard: $bookDate]
        // BpNr: Factuur uid / boekstuknummer [standaard: $claim['BpNr']]
        // InId: Nummer client side (factuurnummer) [standaard: $invoiceId]
        // Ds: Omschrijving [standaard: $claim['Ds']]
        // AmCr: Totaal credit [standaard: $total]
        // AmDe: Totaal debet [standaard: $totalLedger of $totalVatLedger]
        // DaEx: Vervaldatum [standaard: $bookDate]
        // BlPa: Betaalpartij [standaard: 1]
        // VaId: Btwcode [standaard: 6]
        // DiC1: Afdeling [standaard: 1900]
        // DiC2: Kostendrager/product [standaard: 800]

        $defaults = [
            'VaAs' => 3,
            'AcNr' => 6800,
            'EnDa' => date('Y-m-d'),
            'BpDa' => date('Y-m-d'),
            'DaEx' => date('Y-m-d'),
            'BlPa' => 1,
            'VaId' => 6,
            'DiC1' => 1900,
            'DiC2' => 800,
        ];

        $merged = array_merge($defaults, $data);

        $structured = [
            'Element' => [
                '@attributes' => ['SbId' => ''],
                'Fields' => [
                    '@attributes' => ['Action' => 'insert'],
                    'VaAs' => $merged['VaAs'],
                    'AcNr' => $merged['AcNr'],
                    'EnDa' => $merged['EnDa'],
                    'BpDa' => $merged['BpDa'],
                    'BpNr' => $merged['BpNr'] ?? '',
                    'InId' => $merged['InId'] ?? '',
                    'Ds' => $merged['Ds'] ?? '',
                    'AmCr' => $merged['AmCr'] ?? 0,
                    'AmDe' => $merged['AmDe'] ?? 0,
                    'DaEx' => $merged['DaEx'],
                    'BlPa' => $merged['BlPa'],
                    'VaId' => $merged['VaId'],
                    'DiC1' => $merged['DiC1'],
                    'DiC2' => $merged['DiC2'],
                ],
                'Objects' => [
                    'KnSubjectLink' => [
                        'Element' => [
                            '@attributes' => ['SbId' => '']
                        ]
                    ]
                ]
            ]
        ];

        $xml = ArrayToXml::convert($structured, 'KnSubject', true, 'UTF-8');
        $this->updateConnector->SetXML($xml);
        $result = $this->updateConnector->Execute();

        return $result;
    }
}
