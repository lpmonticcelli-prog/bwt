<?php

namespace App\Http\Controllers;

use App\Models\FechamentoPeriodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FechamentoController extends Controller
{
    /**
     * ==========================================
     * 1. CONSTANTES DA MATRIZ OFICIAL
     * ==========================================
     */
    private const NOMENCLATURA_R1 = 'REGIÃO 1 - REGIÃO DE SP E CAMPINAS';
    private const NOMENCLATURA_R2 = 'REGIÃO 2 - CENTRO DO ESTADO';
    private const NOMENCLATURA_R3 = 'REGIÃO 3 - REGIÕES DISTANTES';
    private const NOMENCLATURA_R4 = 'REGIÃO 4 - BAIXA DEMANDA';

    private const REGRAS_REGIAO = [
        self::NOMENCLATURA_R1 => ['min' => 200.00, 'pct' => 0.02],
        self::NOMENCLATURA_R2 => ['min' => 250.00, 'pct' => 0.03],
        self::NOMENCLATURA_R3 => ['min' => 350.00, 'pct' => 0.03],
        self::NOMENCLATURA_R4 => ['min' => 420.00, 'pct' => 0.04],
    ];

    private const CIDADES_POR_REGIAO = [
        self::NOMENCLATURA_R1 => [
            'AMERICANA', 'CAMPINAS', 'COSMOPOLIS', 'ELIAS FAUSTO', 'HOLAMBRA', 'HORTOLANDIA', 'INDAIATUBA', 'JAGUARIUNA', 'MONTE MOR', 'NOVA ODESSA', 'PAULINIA', 'PEDREIRA', 'SANTA BARBARA DOESTE', 'SUMARE', 'VALINHOS', 'VINHEDO',
            'CAMPO LIMPO PAULISTA', 'ITUPEVA', 'JUNDIAI', 'LOUVEIRA', 'VARZEA PAULISTA',
            'ATIBAIA', 'BOM JESUS DOS PERDOES', 'BRAGANCA PAULISTA', 'ITATIBA', 'JARINU', 'JOANOPOLIS', 'MORUNGABA', 'NAZARE PAULISTA', 'PIRACAIA', 'TUIUTI', 'VARGEM'
        ],
        self::NOMENCLATURA_R2 => [
            'ARARAS', 'CONCHAL', 'CORDEIROPOLIS', 'IRACEMAPOLIS', 'LEME', 'LIMEIRA', 'SANTA CRUZ DA CONCEICAO', 'SANTA GERTRUDES',
            'AGUAS DE SAO PEDRO', 'CAPIVARI', 'CHARQUEADA', 'JUMIRIM', 'MOMBUCA', 'PIRACICABA', 'RAFARD', 'RIO DAS PEDRAS', 'SALTINHO', 'SANTA MARIA DA SERRA', 'SAO PEDRO', 'TIETE',
            'PIRASSUNUNGA', 'PORTO FERREIRA', 'SANTA CRUZ DAS PALMEIRAS', 'AGUAI',
            'ARTUR NOGUEIRA', 'ENGENHEIRO COELHO', 'ESTIVA GERBI', 'ITAPIRA', 'MOGI GUACU', 'MOGI MIRIM', 'SANTO ANTONIO DE POSSE',
            'AGUAS DE LINDOIA', 'AMPARO', 'LINDOIA', 'MONTE ALEGRE DO SUL', 'PEDRA BELA', 'PINHALZINHO', 'SERRA NEGRA', 'SOCORRO',
            'ALUMINIO', 'ARACARIGUAMA', 'ARACOIABA DA SERRA', 'CABREUVA', 'CAPELA DO ALTO', 'IPERO', 'ITU', 'MAIRINQUE', 'PORTO FELIZ', 'SALTO', 'SALTO DE PIRAPORA', 'SAO ROQUE', 'SARAPUI', 'SOROCABA', 'VOTORANTIM', 'CERQUILHO', 'TATUI', 'BOITUVA',
            'ARUJA', 'GUARULHOS', 'SANTA ISABEL', 
            'COTIA', 'EMBU DAS ARTES', 'EMBUGUACU', 'ITAPECERICA DA SERRA', 'JUQUITIBA', 'SAO LOURENCO DA SERRA', 'TABOAO DA SERRA', 'VARGEM GRANDE PAULISTA',
            'BARUERI', 'CAJAMAR', 'CARAPICUIBA', 'ITAPEVI', 'JANDIRA', 'OSASCO', 'PIRAPORA DO BOM JESUS', 'SANTANA DE PARNAIBA',
            'CAIEIRAS', 'FRANCISCO MORATO', 'FRANCO DA ROCHA', 'MAIRIPORA',
            'DIADEMA', 'MAUA', 'RIBEIRAO PIRES', 'RIO GRANDE DA SERRA', 'SANTO ANDRE', 'SAO BERNARDO DO CAMPO', 'SAO CAETANO DO SUL', 'SAO PAULO',
            'BIRITIBA MIRIM', 'FERRAZ DE VASCONCELOS', 'GUARAREMA', 'ITAQUAQUECETUBA', 'MOGI DAS CRUZES', 'POA', 'SALESOPOLIS', 'SUZANO'
        ],
        self::NOMENCLATURA_R3 => [
            'AURIFLAMA', 'FLOREAL', 'GASTAO VIDIGAL', 'GENERAL SALGADO', 'GUZOLANDIA', 'MAGDA', 'NOVA CASTILHO', 'NOVA LUZITANIA', 'SAO JOAO DE IRACEMA',
            'MACAUBAL', 'MONCOES', 'MONTE APRAZIVEL', 'NEVES PAULISTA', 'NHANDEARA', 'NIPOA', 'POLONI', 'SEBASTIANOPOLIS DO SUL', 'UNIAO PAULISTA',
            'IRAPUA', 'ITAJOBI', 'MARAPOAMA', 'NOVO HORIZONTE', 'SALES', 'URUPES',
            'ALTO ALEGRE', 'BARBOSA', 'BILAC', 'BRAUNA', 'BREJO ALEGRE', 'BURITAMA', 'CLEMENTINA', 'COROADOS', 'GABRIEL MONTEIRO', 'LOURDES', 'LUIZIANIA', 'PIACATU', 'SANTOPOLIS DO AGUAPEI', 'TURIUBA',
            'LUCIANOPOLIS', 'PAULISTANIA', 'PIRAJUI', 'PIRATININGA', 'PONGAI', 'PRESIDENTE ALVES', 'REGINOPOLIS', 'UBIRAJARA', 'URU',
            'DRACENA', 'JUNQUEIROPOLIS', 'MONTE CASTELO', 'NOVA GUATAPORANGA', 'OURO VERDE', 'PANORAMA', 'PAULICEIA', 'SANTA MERCEDES', 'TUPI PAULISTA',
            'ADAMANTINA', 'FLORA RICA', 'FLORIDA PAULISTA', 'INUBIA PAULISTA', 'IRAPURU', 'LUCELIA', 'MARIAPOLIS', 'OSVALDO CRUZ', 'PACAEMBU', 'PARAPUA', 'PRACINHA', 'RINOPOLIS', 'SAGRES', 'SALMOURAO',
            'CATANDUVA', 'ELISIARIO', 'SANTA ADELIA', 'SAO CARLOS', 'ARARAQUARA', 'MATAO', 'IBATE', 'RIO CLARO', 'SANTA SALETE', 'JALES', 'SANTA FE DO SUL', 'TRES FRONTEIRAS', 'SAO JOSE DO RIO PRETO', 'FERNANDOPOLIS', 'MERIDIANO', 'COSMORAMA', 'VOTUPORANGA', 'BALSAMO', 'URANIA', 'CEDRAL', 'TANABI', 'ESTRELA DOESTE', 'MIRASSOL',
            'AGUAS DA PRATA', 'CACONDE', 'CASA BRANCA', 'DIVINOLANDIA', 'ESPIRITO SANTO DO PINHAL', 'ITOBI', 'MOCOCA', 'SANTO ANTONIO DO JARDIM', 'SAO JOAO DA BOA VISTA', 'SAO JOSE DO RIO PARDO', 'SAO SEBASTIAO DA GRAMA', 'TAMBAU', 'TAPIRATIBA', 'VARGEM GRANDE DO SUL',
            'CACAPAVA', 'IGARATA', 'JACAREI', 'PINDAMONHANGABA', 'SANTA BRANCA', 'SAO JOSE DOS CAMPOS', 'TAUBATE', 'TREMEMBE',
            'ALTINOPOLIS', 'BATATAIS', 'CAJURU', 'CASSIA DOS COQUEIROS', 'SANTA CRUZ DA ESPERANCA', 'SANTO ANTONIO DA ALEGRIA',
            'OLIMPIA', 'BARRETOS', 'COLINA', 'TERRA ROXA',
            'ARCOIRIS', 'BASTOS', 'HERCULANDIA', 'IACRI', 'QUEIROZ', 'QUINTANA', 'TUPA',
            'ALVARO DE CARVALHO', 'ALVINLANDIA', 'ECHAPORA', 'FERNAO', 'GALIA', 'GARCA', 'LUPERCIO', 'MARILIA', 'OCAUCU', 'ORIENTE', 'OSCAR BRESSANE', 'POMPEIA', 'VERA CRUZ',
            'ADOLFO', 'ALTAIR', 'BADY BASSITT', 'CARDOSO', 'GUAPIACU', 'PARISI', 'PONTES GESTAL', 'RIOLANDIA', 'VALENTIM GENTIL',
            'GUARACI', 'IBIRA', 'ICEM', 'IPIGUA', 'JACI', 'JOSE BONIFACIO', 'MENDONCA', 'ZACARIAS', 'MIRASSOLANDIA', 'NOVA ALIANCA', 'NOVA GRANADA', 'ONDA VERDE', 'ORINDIUVA', 'PALESTINA', 'PAULO DE FARIA', 'PLANALTO', 'POTIRENDABA', 'UBARANA', 'UCHOA',
            'ANDRADINA', 'ARACATUBA', 'AVANHANDAVA', 'BIRIGUI', 'CASTILHO', 'GLICERIO', 'GUARARAPES', 'LINS', 'MURUTINGA DO SUL', 'PENAPOLIS', 'PROMISSAO', 'VALPARAISO',
            'AGUDOS', 'BARRA BONITA', 'BAURU', 'DOIS CORREGOS', 'IGARACU DO TIETE', 'JAU', 'LENCOIS PAULISTA', 'MINEIROS DO TIETE', 'BROTAS', 'TORRINHA', 'PEDERNEIRAS', 'SAO MANUEL',
            'BARRINHA', 'BRODOWSKI', 'CRAVINHOS', 'DUMONT', 'GUATAPARA', 'JARDINOPOLIS', 'LUIZ ANTONIO', 'PONTAL', 'PRADOPOLIS', 'RIBEIRAO PRETO', 'SANTA RITA DO PASSA QUATRO', 'SANTA ROSA DE VITERBO', 'SAO SIMAO',
            'BURITIZAL', 'GUARA', 'IGARAPAVA', 'ITUVERAVA', 'CRISTAIS PAULISTA', 'FRANCA', 'ITIRAPUA', 'JERIQUARA', 'PATROCINIO PAULISTA', 'PEDREGULHO', 'RIBEIRAO CORRENTE', 'RIFAINA', 'SANTA ERNESTINA', 'SAO JOSE DA BELA VISTA', 'TAIACU', 'ARAMINA', 'TAQUARAL', 'TAQUARITINGA', 'VIRADOURO', 'VISTA ALEGRE DO ALTO',
            'CANDIDO RODRIGUES', 'FERNANDO PRESTES', 'GUARIBA', 'MONTE AZUL PAULISTA', 'PIRANGI', 'PITANGUEIRAS', 'TAIUVA', 'JABOTICABAL', 'MONTE ALTO', 'BEBEDOURO', 'COLOMBIA', 'GUAIRA', 'IPUA', 'JABORANDI', 'MIGUELOPOLIS', 'MORRO AGUDO', 'SAO JOAQUIM DA BARRA'
        ],
        self::NOMENCLATURA_R4 => [
            'CAMPOS DO JORDAO', 'MONTEIRO LOBATO', 'SANTO ANTONIO DO PINHAL', 'SAO BENTO DO SAPUCAI',
            'APARECIDA', 'CACHOEIRA PAULISTA', 'CANAS', 'CRUZEIRO', 'GUARATINGUETA', 'LAVRINHAS', 'LORENA', 'PIQUETE', 'POTIM', 'QUELUZ', 'ROSEIRA',
            'ARAPEI', 'AREIAS', 'BANANAL', 'CUNHA', 'PARAIBUNA', 'PARAITINGA', 'SAO JOSE DO BARREIRO', 'SILVEIRAS',
            'CARAGUATATUBA', 'ILHABELA', 'SAO SEBASTIAO', 'UBATUBA',
            'ALFREDO MARCONDES', 'ALVARES MACHADO', 'ANHUMAS', 'CAIABU', 'CAIUA', 'EMILIANOPOLIS', 'ESTRELA DO NORTE', 'EUCLIDES DA CUNHA PAULISTA', 'INDIANA', 'JOAO RAMALHO', 'MARABA PAULISTA', 'MARTINOPOLIS', 'MIRANTE DO PARANAPANEMA', 'NARANDIBA', 'PIQUEROBI', 'PIRAPOZINHO', 'PRESIDENTE BERNARDES', 'PRESIDENTE EPITACIO', 'PRESIDENTE PRUDENTE', 'PRESIDENTE VENCESLAU', 'RANCHARIA', 'REGENTE FEIJO', 'RIBEIRAO DOS INDIOS', 'SANDOVALINA', 'SANTO ANASTACIO', 'SANTO EXPEDITO', 'TACIBA', 'TARABAI', 'TEODORO SAMPAIO', 'ROSANA',
            'APIAI', 'BARRA DO CHAPEU', 'CAPAO BONITO', 'GUAPIARA', 'IPORANGA', 'ITAOCA', 'ITAPIRAPUA PAULISTA', 'RIBEIRA', 'RIBEIRAO BRANCO', 'RIBEIRAO GRANDE',
            'BARRA DO TURVO', 'CAJATI', 'CANANEIA', 'ELDORADO', 'IGUAPE', 'ILHA COMPRIDA', 'JACUPIRANGA', 'JUQUIA', 'MIRACATU', 'PARIQUERAACU', 'REGISTRO', 'SETE BARRAS',
            'ALAMBARI', 'ANGATUBA', 'CAMPINA DO MONTE ALEGRE', 'GUAREI', 'ITAPETININGA', 'SARAPUI',
            'BARIRI', 'BOCAINA', 'BORACEIA', 'ITAJU', 'ITAPUI', 'MACATUBA',
            'AGUAS DE SANTA BARBARA', 'ARANDU', 'AVARE', 'CERQUEIRA CESAR', 'IARAS', 'ITAI', 'ITATINGA', 'PARANAPANEMA',
            'BENTO DE ABREU', 'GUARACAI', 'ILHA SOLTEIRA', 'ITAPURA', 'LAVINIA', 'MIRANDOPOLIS', 'NOVA INDEPENDENCIA', 'PEREIRA BARRETO', 'RUBIACEA', 'SAO JOAO DO PAUDALHO', 'SUD MENNUCCI', 'SUZANAPOLIS',
            'BARAO DE ANTONINA', 'BOM SUCESSO DE ITARARE', 'BURI', 'CORONEL MACEDO', 'ITABERA', 'ITAPEVA', 'ITAPORANGA', 'ITARARE', 'NOVA CAMPINA', 'RIVERSUL', 'STO ANT DO ARACANGUA', 'TAQUARITUBA', 'TAQUARIVAI',
            'BERTIOGA', 'CUBATAO', 'GUARUJA', 'PRAIA GRANDE', 'SANTOS', 'SAO VICENTE',
            'ITANHAEM', 'ITARIRI', 'MONGAGUA', 'PEDRO DE TOLEDO', 'PERUIBE',
            'ASSIS', 'BORA', 'CAMPOS NOVOS PAULISTA', 'CANDIDO MOTA', 'CRUZALIA', 'FLORINEA', 'IBIRAREMA', 'IEPE', 'LUTECIA', 'MARACAI', 'NANTES', 'PALMITAL', 'PARAGUACU PAULISTA', 'PEDRINHAS PAULISTA', 'PLATINA', 'QUATA', 'TARUMA',
            'BERNARDINO DE CAMPOS', 'CANITAR', 'CHAVANTES', 'ESPIRITO SANTO DO TURVO', 'FARTURA', 'IPAUSSU', 'MANDURI', 'OLEO', 'OURINHOS', 'PIRAJU', 'RIBEIRAO DO SUL', 'SALTO GRANDE', 'SANTA CRUZ DO RIO PARDO', 'SAO PEDRO DO TURVO', 'SARUTAIA', 'TAGUAI', 'TEJUPA', 'TIMBURI',
            'ALVARES FLORENCE', 'AMERICO DE CAMPOS', 'GUARANI DOESTE', 'INDIAPORA', 'MACEDONIA', 'MIRA ESTRELA', 'OUROESTE', 'PEDRANOPOLIS', 'SAO JOAO DAS DUAS PONTES', 'TURMALINA',
            'CESARIO LANGE', 'LARANJAL PAULISTA', 'PEREIRAS', 'PORANGABA', 'QUADRA', 'TORRE DE PEDRA',
            'IBIUNA', 'PIEDADE', 'PILAR DO SUL', 'SAO MIGUEL ARCANJO', 'TAPIRAI',
            'ANHEMBI', 'BOFETE', 'BOTUCATU', 'CONCHAS', 'PARDINHO', 'PRATANIA',
            'APARECIDA DOESTE', 'ASPASIA', 'DIRCE REIS', 'DOLCINOPOLIS', 'JAMBEIRO', 'MARINOPOLIS', 'MESOPOLIS', 'NOVA CANAA PAULISTA', 'PALMEIRA DOESTE', 'PARANAPUA', 'PONTALINDA', 'POPULINA', 'RUBINEIA', 'SANTA ALBERTINA', 'SANTA CLARA DOESTE', 'SANTA RITA DOESTE', 'SANTANA DA PONTE PENSA', 'SAO FRANCISCO', 'VITORIA BRASIL'
        ]
    ];

    /**
     * ==========================================
     * 2. CRUD PADRÃO DO FECHAMENTO
     * ==========================================
     */
    public function index()
    {
        $fechamentos = FechamentoPeriodo::orderBy('data_inicio', 'desc')
            ->withCount(['fretes', 'faturamentos'])
            ->get();

        return Inertia::render('Fechamentos/Index', [
            'fechamentos' => $fechamentos
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        $vencimento = Carbon::parse($request->data_fim)->addDays(30);

        FechamentoPeriodo::create([
            'titulo' => $request->titulo,
            'data_inicio' => $request->data_inicio,
            'data_fim' => $request->data_fim,
            'data_vencimento' => $vencimento,
            'status' => 'aberto'
        ]);

        return redirect()->route('fechamentos.index');
    }

    public function show($id)
    {
        $fechamento = FechamentoPeriodo::with(['faturamentos', 'fretes'])->findOrFail($id);

        return Inertia::render('Fechamentos/Show', [
            'fechamento' => $fechamento
        ]);
    }

    /**
     * ==========================================
     * 3. UPLOAD E PERSISTÊNCIA NO BANCO DE DADOS
     * ==========================================
     */

    // Lado Esquerdo: Receitas (BWT / Sol Fácil)
    public function uploadReceitaBwt(Request $request, $id)
    {
        $fechamento = FechamentoPeriodo::findOrFail($id);
        $request->validate(['files.*' => 'required|file|mimes:xml']);

        if ($request->hasFile('files')) {
            $dadosProcessados = $this->processarLoteXmls($request->file('files'));
            
            foreach ($dadosProcessados as $dado) {
                // Atualiza ou Cria pelo NFe para não duplicar notas no mesmo fechamento
                $fechamento->faturamentos()->updateOrCreate(
                    ['chave_nfe' => $dado['chave_nfe']], 
                    [
                        'chave_cte' => $dado['chave_cte'],
                        'arquivo' => $dado['arquivo'],
                        'cidade_destino' => $dado['cidade_destino'],
                        'regiao_sistema' => $dado['regiao_sistema'],
                        'tem_tde' => $dado['tem_tde'],
                        'valor_carga' => $dado['valor_carga'],
                        'valor_cobrado' => $dado['valor_cobrado'],
                        'valor_frete_cobrado' => $dado['valor_frete_cobrado'],
                        'valor_tde_cobrado' => $dado['valor_tde_cobrado'],
                        'valor_sla' => $dado['valor_sla'],
                        'valor_frete_sla' => $dado['valor_frete_sla'],
                        'valor_tde_sla' => $dado['valor_tde_sla'],
                        'diferenca' => $dado['diferenca'],
                        'status' => $dado['status'],
                        'motivo' => $dado['motivo'],
                        'arquivos_complemento' => json_encode($dado['arquivos_complemento'])
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Lote de Receitas processado com sucesso!');
    }

    // Lado Direito: Custos (E4LOG)
    public function uploadCustoE4log(Request $request, $id)
    {
        $fechamento = FechamentoPeriodo::findOrFail($id);
        $request->validate(['files.*' => 'required|file|mimes:xml']);

        if ($request->hasFile('files')) {
            $dadosProcessados = $this->processarLoteXmls($request->file('files'));
            
            foreach ($dadosProcessados as $dado) {
                // Salva na tabela "fretes"
                $fechamento->fretes()->updateOrCreate(
                    ['chave_nfe' => $dado['chave_nfe']],
                    [
                        'chave_cte' => $dado['chave_cte'],
                        'arquivo' => $dado['arquivo'],
                        'cidade_destino' => $dado['cidade_destino'],
                        'regiao_sistema' => $dado['regiao_sistema'],
                        'tem_tde' => $dado['tem_tde'],
                        'valor_carga' => $dado['valor_carga'],
                        'valor_cobrado' => $dado['valor_cobrado'],
                        'valor_frete_cobrado' => $dado['valor_frete_cobrado'],
                        'valor_tde_cobrado' => $dado['valor_tde_cobrado'],
                        'valor_sla' => $dado['valor_sla'],
                        'valor_frete_sla' => $dado['valor_frete_sla'],
                        'valor_tde_sla' => $dado['valor_tde_sla'],
                        'diferenca' => $dado['diferenca'],
                        'status' => $dado['status'],
                        'motivo' => $dado['motivo'],
                        'arquivos_complemento' => json_encode($dado['arquivos_complemento'])
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Lote de Custos E4LOG processado com sucesso!');
    }


    /**
     * ==========================================
     * 4. MOTOR MATEMÁTICO (MESMO DA AUDITORIA)
     * ==========================================
     */
    private function processarLoteXmls($files)
    {
        $resultadosAtuais = [];

        foreach ($files as $file) {
            $data = $this->parseXml($file);
            if (!$data) continue; 

            $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
            
            // Extrações
            $localDestino         = $this->extractCityAndUf($data);
            $cidadeDestino        = $localDestino['cidade'];
            $ufDestino            = $localDestino['uf'];
            
            $valorCarga           = $this->extractInvoiceValue($data);
            $observacoesTexto     = $this->extractObs($data);
            $tipoCTe              = $this->extractTipoCTe($data);
            
            $temTde               = $this->verificarTde($data, $observacoesTexto, $tipoCTe);
            $tipoOperacao         = $this->extractTipoOperacao($observacoesTexto, $tipoCTe);
            
            $chaveCte             = $this->extractChaveCTe($data, $nomeArquivo);
            $chaveOriginal        = $this->extractChaveOriginal($data);
            $chaveNfe             = $this->extractChaveNFe($data); 
            
            $valoresFaturados     = $this->extractValoresXML($data, $tipoOperacao);
            $valorCobradoOriginal = $valoresFaturados['total'];
            $valorFreteCobrado    = $valoresFaturados['frete'];
            $valorTdeCobrado      = $valoresFaturados['tde'];
            
            $regiaoFaturadaData   = $this->descobrirRegiaoFaturada($observacoesTexto, $valorCarga, $valorCobradoOriginal, $temTde, $tipoOperacao);

            // Região e Fallback
            if ($ufDestino !== 'SP' && $ufDestino !== '') {
                $nomeRegiao = "Tabela " . $ufDestino . " Ausente";
                $percentual = 0;
                $minimo = 0;
            } else {
                $nomeRegiao = $this->getRegiaoPorCidade($cidadeDestino);

                if ($nomeRegiao === '-') {
                    $regFat = strtoupper($regiaoFaturadaData['nome']);
                    if (str_contains($regFat, 'REGIÃO 1')) $nomeRegiao = self::NOMENCLATURA_R1;
                    elseif (str_contains($regFat, 'REGIÃO 2')) $nomeRegiao = self::NOMENCLATURA_R2;
                    elseif (str_contains($regFat, 'REGIÃO 3')) $nomeRegiao = self::NOMENCLATURA_R3;
                    elseif (str_contains($regFat, 'REGIÃO 4')) $nomeRegiao = self::NOMENCLATURA_R4;
                    else $nomeRegiao = 'Indefinida/SP'; 
                    
                    if ($nomeRegiao !== 'Indefinida/SP') $nomeRegiao .= ' (Auto)';
                }
                
                $regiaoBase = str_replace(' (Auto)', '', $nomeRegiao);
                $percentual = self::REGRAS_REGIAO[$regiaoBase]['pct'] ?? 0;
                $minimo     = self::REGRAS_REGIAO[$regiaoBase]['min'] ?? 0;
            }

            // Matemática SLA
            $valorFreteSla = 0;
            $valorTdeSla = 0;
            $valorSlaCorreto = 0;
            $diferenca = 0;

            if ($tipoOperacao === 'Complemento') {
                $valorFreteSla = 0; 
                $valorTdeSla = 0;
            } else if ($nomeRegiao !== "Tabela {$ufDestino} Ausente" && $nomeRegiao !== 'Indefinida/SP') {
                
                $freteCalculado = $valorCarga * $percentual;
                $valorFreteSla = max($minimo, $freteCalculado);
                $valorTdeSla = $temTde ? max(160.00, $valorFreteSla * 0.20) : 0;
                
                $valorSlaCorreto = $valorFreteSla + $valorTdeSla;
                $diferenca       = $valorSlaCorreto - $valorCobradoOriginal;
            }

            // Status e Motivo
            if (str_contains($nomeRegiao, 'Ausente') || $nomeRegiao === 'Indefinida/SP') {
                $status = 'Alerta';
                $motivo = "Requer revisão manual.";
                $diferenca = 0; 
            } else {
                if (round($diferenca, 2) > 0) {
                    $status = 'Divergente';
                    $motivo = "Cobrado a MENOS.";
                } elseif (round($diferenca, 2) < 0) {
                    $status = 'Divergente';
                    $motivo = "Cobrado a MAIS.";
                } else {
                    $status = 'Validado';
                    $motivo = "Validação 100% Exata.";
                }
            }

            $resultadosAtuais[] = [
                'chave_cte'           => $chaveCte,
                'chave_nfe'           => $chaveNfe, 
                'arquivo'             => $nomeArquivo,
                'cidade_destino'      => $cidadeDestino . ($ufDestino ? " - {$ufDestino}" : ''),
                'regiao_sistema'      => $nomeRegiao,
                'percentual_sistema'  => ($percentual > 0) ? ($percentual * 100) . '%' : '-',
                'regiao_faturada'     => $regiaoFaturadaData['nome'],
                'percentual_faturado' => $regiaoFaturadaData['pct'],
                'tem_tde'             => $temTde ? 'Sim' : 'Não',
                'tipo_operacao'       => $tipoOperacao,
                'chave_original'      => $chaveOriginal,
                'valor_carga'         => (float) $valorCarga,
                
                'valor_cobrado'       => (float) $valorCobradoOriginal,
                'valor_frete_cobrado' => (float) $valorFreteCobrado,
                'valor_tde_cobrado'   => (float) $valorTdeCobrado,
                
                'valor_sla'           => (float) $valorSlaCorreto,
                'valor_frete_sla'     => (float) $valorFreteSla, 
                'valor_tde_sla'       => (float) $valorTdeSla,   
                
                'diferenca'           => (float) $diferenca,
                'status'              => $status,
                'motivo'              => $motivo 
            ];
        }

        // Devolve os dados agrupados (fundindo os complementos aos CTes pais)
        return $this->agruparResultados($resultadosAtuais);
    }

    private function agruparResultados($dadosFlat) {
        $agrupados = [];

        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] !== 'Complemento') {
                $chave = $item['chave_cte'];
                $item['arquivos_complemento'] = []; 
                $agrupados[$chave] = $item;
            }
        }

        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] === 'Complemento') {
                $chavePai = $item['chave_original'];

                if ($chavePai && isset($agrupados[$chavePai])) {
                    // Soma Faturada
                    $agrupados[$chavePai]['valor_cobrado']       += $item['valor_cobrado'];
                    $agrupados[$chavePai]['valor_frete_cobrado'] += $item['valor_frete_cobrado'];
                    $agrupados[$chavePai]['valor_tde_cobrado']   += $item['valor_tde_cobrado'];
                    
                    $agrupados[$chavePai]['tem_tde'] = 'Sim';

                    // Recálculo SLA TDE com regra de 160
                    if(!str_contains($agrupados[$chavePai]['regiao_sistema'], 'Ausente') && $agrupados[$chavePai]['regiao_sistema'] !== 'Indefinida/SP'){
                        
                        $freteSlaPai = $agrupados[$chavePai]['valor_frete_sla'];
                        
                        if ($agrupados[$chavePai]['valor_tde_sla'] == 0) {
                            $agrupados[$chavePai]['valor_tde_sla'] = max(160.00, $freteSlaPai * 0.20);
                            $agrupados[$chavePai]['valor_sla'] = $freteSlaPai + $agrupados[$chavePai]['valor_tde_sla'];
                        }

                        $agrupados[$chavePai]['diferenca'] = $agrupados[$chavePai]['valor_sla'] - $agrupados[$chavePai]['valor_cobrado'];
                        
                        $diff = $agrupados[$chavePai]['diferenca'];
                        $agrupados[$chavePai]['status'] = round($diff, 2) == 0 ? 'Validado' : 'Divergente';
                        
                        if (round($diff, 2) > 0) {
                            $agrupados[$chavePai]['motivo'] = "Cobrado a MENOS.";
                        } elseif (round($diff, 2) < 0) {
                            $agrupados[$chavePai]['motivo'] = "Cobrado a MAIS.";
                        } else {
                            $agrupados[$chavePai]['motivo'] = "Validação 100% Exata.";
                        }
                    }

                    $agrupados[$chavePai]['arquivos_complemento'][] = $item['arquivo'];
                } else {
                    $chave = $item['chave_cte'];
                    $item['arquivos_complemento'] = [];
                    $agrupados[$chave] = $item;
                }
            }
        }

        return array_values($agrupados);
    }

    private function descobrirRegiaoFaturada($obs, $valorCarga, $valorCobrado, $temTde, $tipoOperacao) {
        if ($tipoOperacao === 'Complemento') return ['nome' => 'Complemento', 'pct' => '-'];
        if ($valorCobrado <= 0) return ['nome' => 'Não Faturado', 'pct' => '-'];

        foreach (self::REGRAS_REGIAO as $nome => $regra) {
            $valorFrete = max($regra['min'], $valorCarga * $regra['pct']);
            $tde = $temTde ? max(160.00, $valorFrete * 0.20) : 0;
            
            if (abs(($valorFrete + $tde) - $valorCobrado) <= 1.50) {
                return ['nome' => $nome . ' (Calc)', 'pct' => ($regra['pct'] * 100) . '%'];
            }
        }

        if (preg_match('/REGI[AÃ]O\s*(\d)/i', $obs, $matches)) {
            $num = $matches[1];
            if ($num == '1') return ['nome' => self::NOMENCLATURA_R1, 'pct' => '2%'];
            if ($num == '2') return ['nome' => self::NOMENCLATURA_R2, 'pct' => '3%'];
            if ($num == '3') return ['nome' => self::NOMENCLATURA_R3, 'pct' => '3%'];
            if ($num == '4') return ['nome' => self::NOMENCLATURA_R4, 'pct' => '4%'];
            
            return ['nome' => 'Região ' . $num, 'pct' => '-'];
        }

        return ['nome' => 'Indefinida', 'pct' => '-'];
    }

    private function getRegiaoPorCidade(string $cidade): string {
        $cidadeLpa = preg_replace('/[^A-Z0-9 ]/', '', Str::ascii(strtoupper($cidade)));
        $cidadeLpa = trim(preg_replace('/\s+/', ' ', $cidadeLpa));

        foreach (self::CIDADES_POR_REGIAO as $regiao => $cidades) {
            if (in_array($cidadeLpa, $cidades, true)) return $regiao;
        }

        foreach (self::CIDADES_POR_REGIAO as $regiao => $cidades) {
            foreach ($cidades as $c) {
                if (str_contains($cidadeLpa, $c) || str_contains($c, $cidadeLpa)) return $regiao;
            }
        }

        return '-';
    }

    private function parseXml($file) {
        try {
            $xmlContent = file_get_contents($file->getPathname());
            $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
            $xmlObj = simplexml_load_string($xmlContent);
            return json_decode(json_encode($xmlObj), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractChaveCTe($data, $nomeArquivo) {
        $ch = '';
        if (isset($data['protCTe']['infProt']['chCTe'])) {
            $ch = (string) $data['protCTe']['infProt']['chCTe'];
        } else {
            $base = $this->getBaseNode($data);
            if ($base && isset($base['@attributes']['Id'])) {
                $ch = str_replace('CTe', '', $base['@attributes']['Id']);
            } elseif (preg_match('/\d{44}/', $nomeArquivo, $matches)) {
                $ch = $matches[0];
            }
        }
        $ch = preg_replace('/[^0-9]/', '', $ch); 
        return empty($ch) ? Str::uuid()->toString() : $ch;
    }
    
    private function extractChaveNFe($data) {
        $base = $this->getBaseNode($data);
        if (isset($base['infCTeNorm']['infDoc']['infNFe']['chave'])) {
            return (string) $base['infCTeNorm']['infDoc']['infNFe']['chave'];
        }
        
        $obs = $this->extractObs($data);
        if (preg_match('/NF\s*[:\-]?\s*(\d+)/i', $obs, $matches)) {
            return 'NF_EXTRAIDA_' . $matches[1]; 
        }

        return 'SEM_NFE_' . Str::uuid()->toString(); 
    }

    private function extractChaveOriginal($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCteComp']['chCTe'])) {
            return preg_replace('/[^0-9]/', '', (string) $base['infCteComp']['chCTe']);
        }
        if ($base && isset($base['infCteComp']['chave'])) {
            return preg_replace('/[^0-9]/', '', (string) $base['infCteComp']['chave']);
        }
        return null;
    }
    
    private function extractTipoOperacao($observacoes, $tipoCTe) { 
        $obs = strtoupper($observacoes);
        if (str_contains($obs, 'DEVOLUCAO') || str_contains($obs, 'RETORNO')) return 'Devolução'; 
        if (str_contains($obs, 'REENTREGA')) return 'Reentrega'; 
        if ((string)$tipoCTe === '1' || str_contains($obs, 'COMPL')) return 'Complemento'; 
        return 'Entrega'; 
    }
    
    private function verificarTde($data, $obs, $tipoCTe) {
        if (str_contains(strtoupper($obs), 'TDE') || str_contains(strtoupper($obs), 'RURAL')) return true;
        if ((string)$tipoCTe === '0') {
            $base = $this->getBaseNode($data);
            if ($base && isset($base['vPrest']['Comp'])) {
                $comps = $base['vPrest']['Comp'];
                if (isset($comps['xNome'])) $comps = [$comps];
                foreach ($comps as $c) {
                    $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                    if (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE')) return true;
                }
            }
        }
        return false;
    }
    
    private function getBaseNode($data) { 
        if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; 
        if (isset($data['infCte'])) return $data['infCte']; 
        return null; 
    }
    
    private function extractCityAndUf($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['dest']['enderDest']['xMun'])) {
            return [
                'cidade' => strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')),
                'uf'     => strtoupper((string) ($base['dest']['enderDest']['UF'] ?? ''))
            ];
        }
        return ['cidade' => 'Desconhecida', 'uf' => '']; 
    }
    
    private function extractInvoiceValue($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; 
        if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga']; 
        return 0.00; 
    }
    
    private function extractValoresXML($data, $tipoOperacao) {
        $base = $this->getBaseNode($data);
        $total = 0;
        $tde = 0;

        if ($base && isset($base['vPrest']['vTPrest'])) {
            $total = (float) $base['vPrest']['vTPrest'];
        }

        if ($base && isset($base['vPrest']['Comp'])) {
            $comps = $base['vPrest']['Comp'];
            if (isset($comps['xNome'])) {
                $comps = [$comps];
            }
            foreach ($comps as $c) {
                $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                $valor = (float)($c['vComp'] ?? 0);
                
                if (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE') || str_contains($nome, 'TRT')) {
                    $tde += $valor;
                }
            }
        }

        if ($tipoOperacao === 'Complemento' && $tde == 0) {
            $tde = $total;
        }

        $frete = $total - $tde;

        return [
            'total' => $total,
            'frete' => max(0, $frete),
            'tde'   => $tde
        ];
    }
    
    private function extractObs($data) { 
        $base = $this->getBaseNode($data); 
        $obs = '';
        if ($base && isset($base['compl']['xObs'])) $obs .= (string) $base['compl']['xObs']; 
        return strtoupper($obs);
    }
    
    private function extractTipoCTe($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; 
        return '0'; 
    }
}