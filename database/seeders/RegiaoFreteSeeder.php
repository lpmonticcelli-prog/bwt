<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RegiaoFrete;

class RegiaoFreteSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cidades que coincidem nas DUAS tabelas (E4LOG e Solfácil cobram Região 1)
        $cidadesMuteuas = [
            'Americana', 'Campinas', 'Cosmópolis', 'Elias Fausto', 'Holambra', 
            'Hortolândia', 'Indaiatuba', 'Jaguariúna', 'Monte Mor', 'Nova Odessa', 
            'Paulínia', 'Pedreira', 'Santa Bárbara d\'Oeste', 'Sumaré', 'Valinhos', 
            'Vinhedo', 'Campo Limpo Paulista', 'Itupeva', 'Jundiaí', 'Louveira', 'Várzea Paulista'
        ];

        foreach ($cidadesMuteuas as $cidade) {
            RegiaoFrete::updateOrCreate(['cidade' => $cidade], [
                'regiao_e4log' => 1,
                'regiao_solfacil' => 1
            ]);
        }

        // 2. Cidades SÓ da E4LOG (Região 6) - BWT paga, mas não tem regra Solfácil nesta tabela
        $cidadesE4logExclusivas = [
            'Atibaia', 'Bom Jesus dos Perdões', 'Bragança Paulista', 'Itatiba', 
            'Jarinu', 'Joanópolis', 'Morungaba', 'Nazaré Paulista', 'Piracaia', 
            'Tuiuti', 'Vargem'
        ];

        foreach ($cidadesE4logExclusivas as $cidade) {
            RegiaoFrete::updateOrCreate(['cidade' => $cidade], [
                'regiao_e4log' => 1,
                'regiao_solfacil' => null // Alerta de prejuízo para BWT
            ]);
        }

        // 3. Cidades SÓ da Solfácil (Região 5) - BWT Fatura, mas a E4LOG não cobra nesta tabela
        $cidadesSolfacilExclusivas = [
            'Arujá', 'Barueri', 'Cabreúva', 'Caieiras', 'Cajamar', 'Carapicuíba', 
            'Cotia', 'Diadema', 'Embu das Artes', 'Embu-Guaçu', 'Francisco Morato', 
            'Franco da Rocha', 'Guarulhos', 'Itapecerica da Serra', 'Itapevi', 'Itu', 
            'Jandira', 'Juquitiba', 'Mairiporã', 'Mauá', 'Osasco', 'Pirapora do Bom Jesus', 
            'Ribeirão Pires', 'Rio Grande da Serra', 'Salto', 'Santa Isabel', 
            'Santana de Parnaíba', 'Santo André', 'São Bernardo do Campo', 
            'São Caetano do Sul', 'São Lourenço da Serra', 'São Paulo', 
            'Taboão da Serra', 'Vargem Grande Paulista'
        ];

        foreach ($cidadesSolfacilExclusivas as $cidade) {
            RegiaoFrete::updateOrCreate(['cidade' => $cidade], [
                'regiao_e4log' => null, // Esperando definição de custo
                'regiao_solfacil' => 1
            ]);
        }
    }
}