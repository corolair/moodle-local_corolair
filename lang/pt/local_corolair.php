<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for the Raison Local Plugin.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adhocqueued'] = 'A sincronização com os serviços do Raison deveria ter começado na sua tarefa ad hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Caso contrário, peça a geração de uma chave de API <a href="{$a->trainer_page_link}">aqui</a>.';
$string['apikey'] = 'Chave de API do Raison';
$string['apikeydesc'] = 'Esta chave é gerada durante a instalação do plugin. Mantenha-a em segredo. Pode ser pedida pela equipa de suporte do Raison.';
$string['apikeymissing'] = 'Chave de API não encontrada na resposta da API do Raison.';
$string['apikeyrotate'] = 'Renovar a chave de API';
$string['apikeyrotateconfirm'] = 'Esta ação gera uma nova chave de API do Raison e invalida imediatamente a chave atual. Continuar?';
$string['apikeyrotatefailed'] = 'A renovação da chave de API do Raison falhou. Verifique os serviços web e a conectividade e tente novamente.';
$string['apikeyrotatenotoken'] = 'Não foi encontrado nenhum token de serviço web do Raison. Conclua a configuração do plugin antes de renovar a chave de API.';
$string['apikeyrotatesuccess'] = 'Foi gerada uma nova chave de API do Raison. A chave anterior deixou de ser válida.';
$string['apikeyset'] = 'A chave de API foi definida com sucesso.';
$string['assignmanagerrolecapability'] = 'Atribuir o papel Gestor do Raison através da API de integração de âmbito restrito.';
$string['cachedef_widgetcourses'] = 'Lista de disciplinas do Raison: as disciplinas deste site que têm um assistente Raison, atualizada a cada cinco minutos.';
$string['calendlydemo'] = 'Para que o possamos ajudar de forma eficaz, descreva primeiro o seu caso de utilização e as suas necessidades numa chamada de descoberta com a equipa Raison. Depois de compreendermos os seus requisitos, os nossos programadores darão prioridade à resolução dos problemas de ligação com a sua instância do Moodle. Marque a sua chamada <strong> <a href="https://discoverycall.raison.is/" target="_blank">aqui</a> </strong>.';
$string['capabilityassignerror'] = 'Não foi possível atribuir a capacidade "{$a}" ao papel.';
$string['capabilityfalse'] = 'Falso';
$string['capabilitytrue'] = 'Verdadeiro';
$string['choosertoolnotincourse'] = 'Esta ferramenta externa pertence a outra disciplina, pelo que o Raison não pode alterar se aparece no seletor de atividades desta disciplina.';
$string['corolair:assignmanagerrole'] = 'Atribuir o papel Gestor do Raison através da API de integração de âmbito restrito.';
$string['corolair:createtutor'] = 'Permite ao utilizador criar e gerir tutores no plugin Raison.';
$string['corolair:viewroles'] = 'Permite ao utilizador obter os metadados dos papéis do Moodle para a integração com o Raison.';
$string['coursenodetitle'] = 'Assistente de IA Raison';
$string['createtutorcapability'] = 'Permite que os utilizadores criem e giram Tutores de IA no Raison';
$string['createtutorcapabilitydesc'] = 'O utilizador não poderá criar Tutores de IA a partir de disciplinas que não pode gerir. Se for definido como Falso, poderá criar Tutores de IA a partir de disciplinas em que está apenas inscrito.';
$string['curlerror'] = 'Ocorreu um erro na comunicação com a API do Raison. Não foi possível registar a sua instância do Moodle; tente novamente. Se o erro persistir, contacte a equipa Raison.';
$string['deregisterfailed'] = 'O Raison não conseguiu confirmar a anulação do registo remoto após as tentativas disponíveis. O acesso ao Moodle foi revogado e as credenciais locais da integração foram removidas, mas a eliminação dos dados já detidos pelo Raison não foi confirmada tecnicamente. Contacte contact@raison.is, ao abrigo do seu contrato de serviço ou de tratamento de dados aplicável, e indique o URL deste site Moodle para concluir o processo de eliminação.';
$string['disabletokenrotation'] = 'Desativar a renovação do token de serviço web';
$string['disabletokenrotationdesc'] = 'Por predefinição, o token de serviço web do Raison expira ao fim de 15 dias e é substituído automaticamente antes de expirar. Ative esta opção apenas se não for possível contar com essa substituição automática, por exemplo quando o cron do Moodle não é executado regularmente ou quando o acesso de saída ao Raison não está garantido. Três consequências: o token deixa de expirar; sempre que esta definição mudar, o token anterior continua utilizável durante mais 7 dias, no máximo, para que os pedidos já em curso não sejam interrompidos; e o Raison deixa de verificar periodicamente se este site ainda concede as funções de que a integração necessita, pelo que um erro de configuração é detetado mais tarde do que seria de outra forma. A aplicação de uma alteração requer o cron e uma chamada bem-sucedida ao Raison; o progresso é indicado acima.';
$string['disclosureaccess'] = 'Acesso';
$string['disclosureaccessread'] = 'Leitura';
$string['disclosureaccesswrite'] = 'Escrita';
$string['disclosureacknowledgebutton'] = 'Confirmo que li esta declaração de integração';
$string['disclosureacknowledgmentnote'] = 'Esta confirmação atesta que analisou o âmbito da integração. Não é apresentada como um consentimento jurídico.';
$string['disclosureallowlist'] = 'O token está restrito à lista fixa de funções permitidas do serviço corolair_rest. Não pode invocar funções de serviço web do Moodle fora dessa lista.';
$string['disclosurecapabilitiesheading'] = 'Porque estão envolvidas capacidades do Moodle';
$string['disclosurecapabilitiesintro'] = 'O Moodle avalia as capacidades do proprietário do token, além da lista de funções permitidas do serviço. O token pertence a uma conta de serviço dedicada que não é administradora do site e que detém exatamente as capacidades listadas abaixo, e nenhuma outra. Cada capacidade listada é exigida por uma função específica da lista de funções permitidas acima.';
$string['disclosurecapability'] = 'Capacidade';
$string['disclosurecapcompletion'] = 'Permite leituras de conclusão reservadas a futuras funcionalidades de tutoria e de análise baseadas no progresso. Os dados de conclusão não são processados atualmente.';
$string['disclosurecapcontent'] = 'Permite ler o conteúdo dos tipos de atividade suportados e transferir os ficheiros correspondentes.';
$string['disclosurecapcoursevisibility'] = 'Permite ler a estrutura de disciplinas e categorias, incluindo itens ocultos, como material de origem e contexto organizacional dos tutores. A conta de serviço não está inscrita em nenhuma disciplina: é a capacidade course:view que lhe dá acesso de leitura às disciplinas. Lê também atividades e secções sujeitas a restrições de acesso, incluindo as configuradas para ficarem totalmente ocultas: o Moodle omite-as por completo da sua resposta, e uma sincronização de conteúdo não consegue distinguir uma atividade omitida de uma atividade eliminada.';
$string['disclosurecapexam'] = 'Permite que o fluxo específico de colocação de exames crie, renomeie e elimine a atividade Ferramenta externa que contém um exame do Raison. Esta é a única parte da integração que escreve numa disciplina, e só pode alcançar as atividades criadas pelo próprio Raison: o plugin regista cada colocação que efetua, e as funções de renomear e de eliminar recusam qualquer atividade ausente desse registo. Uma atividade Ferramenta externa adicionada por um professor, ou pertencente a outro produto, fica fora de alcance. A colocação é recusada à partida, a menos que a ferramenta selecionada seja iniciada a partir do anfitrião do Raison configurado. O mesmo fluxo pode mostrar a ferramenta do Raison no seletor de atividades de uma disciplina, segundo a mesma regra de anfitrião; qualquer outra ferramenta, ou uma que pertença a outra disciplina, é recusada.';
$string['disclosurecapidentity'] = 'Permite ler os campos de identidade e de e-mail necessários para a correspondência de contas, os convites, a personalização e a delimitação do acesso. Estas capacidades apenas desbloqueiam a leitura de campos: não permitem nenhuma alteração a uma conta de utilizador. O Moodle classifica moodle/course:useremail como escrita e designa-a como "Ativar/desativar endereço de e-mail", o que exagera o seu alcance: não comporta nenhum indicador de risco e concede apenas a possibilidade de ler um endereço que o utilizador optou por não mostrar. Está incluída porque, sem ela, o Moodle omite totalmente o endereço em sites que restringiram os seus campos de identidade de utilizador, e o Raison faz a correspondência de contas pelo endereço de e-mail quando não tem um ID de utilizador do Moodle guardado.';
$string['disclosurecapparticipants'] = 'Permite ler os participantes e os conteúdos de todos os grupos, para que o acesso possa ser delimitado corretamente.';
$string['disclosurecapprotocol'] = 'Permite que o token chame o serviço web REST do Moodle. Sem ela, todos os pedidos são recusados antes de qualquer função ou capacidade ser considerada.';
$string['disclosurecaproleassign'] = 'Permite ler os papéis do Moodle e atribuir o papel Gestor do Raison a um formador convidado a partir do Raison. Só pode atribuir esse único papel, que por sua vez não concede nada além deste plugin.';
$string['disclosuredataaccess'] = 'Inscrições, papéis, grupos e relações entre participantes são utilizados para determinar quem pode aceder a cada tutor.';
$string['disclosuredatacompletion'] = 'A conclusão de atividades e de disciplinas está reservada a uma funcionalidade futura baseada no progresso e não é processada atualmente.';
$string['disclosuredatacourse'] = 'Categorias de disciplinas, estrutura, secções, recursos, lições, pacotes SCORM e H5P, vídeos UbiCast e metadados LTI são utilizados como material de origem dos tutores e como contexto organizacional.';
$string['disclosuredataexam'] = 'Quando a funcionalidade de colocação de exames é acionada, as informações da atividade LTI e da colocação são utilizadas para criar, atualizar ou remover a colocação pedida.';
$string['disclosuredataheading'] = 'Utilização de dados pessoais e de aprendizagem';
$string['disclosuredataidentity'] = 'IDs, nomes, e-mails e papéis de administradores e utilizadores suportam o aprovisionamento de contas, os convites, a correspondência de identidades, a personalização e o acesso às sessões.';
$string['disclosuredatasite'] = 'O URL do site Moodle, o nome do site e a identidade do serviço registam e identificam esta instalação.';
$string['disclosurefiletransfer'] = 'A transferência de ficheiros está ativada para este serviço, para que os recursos de disciplina suportados possam ser lidos. O carregamento de ficheiros está desativado: a integração nunca escreve ficheiros no Moodle, e o Moodle controla os carregamentos apenas por essa opção do serviço, e não pela lista de funções permitidas.';
$string['disclosurefunction'] = 'Função de serviço web';
$string['disclosurefunctionsheading'] = 'Lista fixa de funções de serviço web permitidas';
$string['disclosurefunctionsintro'] = 'O plugin normalizado expõe as 28 funções seguintes. Expanda cada grupo para ver os nomes exatos e a classificação de leitura/escrita.';
$string['disclosuregroupcompletion'] = 'Leituras de conclusão';
$string['disclosuregroupcompletiondesc'] = 'Ler a conclusão de atividades e de disciplinas para uma futura tutoria baseada no progresso. Estes dados não são processados atualmente.';
$string['disclosuregroupcontent'] = 'Leituras de disciplinas e de conteúdos de aprendizagem';
$string['disclosuregroupcontentdesc'] = 'Ler catálogos de disciplinas, secções, recursos, lições, pacotes SCORM e H5P, vídeos UbiCast e metadados LTI utilizados para criar e organizar os tutores.';
$string['disclosuregroupenrolment'] = 'Leituras de inscrições, participantes e papéis';
$string['disclosuregroupenrolmentdesc'] = 'Ler as inscrições nas disciplinas, os participantes, as capacidades e os papéis do Moodle utilizados para delimitar o acesso aos tutores.';
$string['disclosuregroupexamplacement'] = 'Colocação de exames e escrita de atividades';
$string['disclosuregroupexamplacementdesc'] = 'Escritas específicas, utilizadas apenas quando um administrador aciona o fluxo de colocação de exames.';
$string['disclosuregroupidentity'] = 'Leituras do site e de identidade';
$string['disclosuregroupidentitydesc'] = 'Ler a identidade do site Moodle e os campos de perfil de utilizador necessários para o registo, a correspondência de contas e a personalização.';
$string['disclosuregrouproleassignment'] = 'Escrita de atribuição do papel de formador';
$string['disclosuregrouproleassignmentdesc'] = 'Atribuir o papel Gestor do Raison quando um convite autorizado de formador é iniciado a partir do Raison.';
$string['disclosureheading'] = 'Analise a declaração de integração do Raison';
$string['disclosureintro'] = 'Antes de qualquer definição de serviço web ser ativada ou de ser criado um token, analise abaixo o limite exato de acesso, a lista de funções e as finalidades dos dados.';
$string['disclosuremissing'] = 'A declaração de integração atual tem de ser confirmada pelo administrador que inicia a configuração.';
$string['disclosureopensource'] = 'O código-fonte do plugin está disponível publicamente para revisão de segurança:';
$string['disclosureplanned'] = 'Utilização prevista';
$string['disclosureposttrialagreements'] = 'Se a sua organização optar por continuar a utilizar o Raison após o período de avaliação gratuito, a continuidade do serviço exigirá acordos formais entre o Raison e a sua organização. Esses acordos definirão as responsabilidades de cada parte e garantirão o alinhamento quanto à proteção de dados, à privacidade, à segurança e aos requisitos regulamentares aplicáveis.';
$string['disclosureprivacycontact'] = 'Para questões sobre o tratamento externo, a conservação ou a eliminação de dados, contacte contact@raison.is.';
$string['disclosurepurpose'] = 'Finalidade';
$string['disclosurerole'] = 'O papel Gestor do Raison é independente do token. Concede local/corolair:createtutor e local/corolair:viewroles a utilizadores interativos do Moodle.';
$string['disclosuresecurityheading'] = 'Propriedade do token e limite de segurança';
$string['disclosureserviceaccountbody'] = 'O token pertence a uma conta dedicada do Moodle chamada "{$a}", criada e mantida por este plugin. Não é administradora do site, não tem palavra-passe, não pode iniciar sessão de forma interativa e detém apenas as capacidades listadas abaixo. A desinstalação do plugin suspende a conta e elimina o seu papel; a conta em si é mantida, sem quaisquer dados pessoais, para que uma reinstalação não acumule duplicados.';
$string['disclosureserviceaccountcontact'] = 'A conta está registada com o endereço {$a} para que qualquer pessoa que a encontre entre os seus utilizadores saiba a quem pertence e a quem perguntar sobre ela. O Moodle nunca lhe envia e-mails: a conta é criada com as notificações por e-mail desativadas e com o endereço oculto para os restantes utilizadores. Se esse endereço já pertencer a uma conta real neste site, a conta de serviço recebe um endereço não encaminhável, para que a conta real não seja afetada.';
$string['disclosureserviceaccountheading'] = 'A conta de serviço dedicada';
$string['disclosurestandardised'] = 'O conjunto de funções do serviço é normalizado e idêntico em todas as instalações suportadas; não é alargado dinamicamente para clientes individuais.';
$string['disclosuretokenowner'] = 'O token de serviço web pertence a uma conta de serviço dedicada, não administradora, e o serviço está restrito para que nenhuma outra conta o possa utilizar. O Moodle avalia cada chamada com as capacidades dessa conta, listadas abaixo.';
$string['disclosuretokenrotationdisabled'] = 'Este site desativou a renovação do token. O token não expira, e o Raison deixou de verificar periodicamente se este site ainda concede as funções listadas abaixo.';
$string['disclosuretokentransfer'] = 'O token é transferido para o Raison por HTTPS para que a integração possa chamar as funções permitidas do Moodle. Por predefinição, expira ao fim de 15 dias e é renovado antes de expirar; um administrador do site pode desativar a renovação e, nesse caso, o token não expira.';
$string['disclosureuninstall'] = 'Na desinstalação, o plugin tenta anular o registo remoto até três vezes antes de revogar o acesso ao serviço web do Moodle e remover as credenciais locais da integração. Se a eliminação remota não puder ser confirmada, os dados transferidos anteriormente continuam sujeitos ao contrato de serviço ou de tratamento de dados aplicável, e o administrador tem de contactar contact@raison.is, indicando o URL do site Moodle, para concluir o processo de eliminação.';
$string['disclosureversion'] = 'Versão da declaração';
$string['errortoken'] = 'Erro ao obter o token';
$string['eventintegrationdisclosureacknowledged'] = 'Declaração de integração confirmada';
$string['eventprivacydeletioncompleted'] = 'Eliminação de privacidade do Raison concluída';
$string['eventremoterequestcompleted'] = 'Pedido remoto ao Raison concluído';
$string['eventwebservicetokenlifecycle'] = 'Ciclo de vida do token de serviço web do Raison atualizado';
$string['excludedmods'] = 'Atividades excluídas';
$string['excludedmodsdesc'] = 'Utilize esta lista para desativar os assistentes em tipos de atividade específicos, por exemplo para impedir que os alunos os utilizem durante as avaliações. Indique uma lista separada por vírgulas de nomes curtos de módulos de atividade (por exemplo, "quiz, assign"). O nome curto é a pasta apresentada no URL da atividade a seguir a \'/mod/\' (por exemplo, \'/mod/quiz/\' → \'quiz\'). Isto também funciona para módulos de atividade fornecidos por plugins externos.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['hideonraisonexam'] = 'Ocultar o Tutor de IA durante os exames do Raison';
$string['hideonraisonexamdesc'] = 'O Raison disponibiliza uma ferramenta de exames com IA baseada em LTI, que talvez ainda não esteja configurada no seu site: se quiser saber mais ou experimentá-la, contacte a equipa Raison através de contact@raison.is. Quando esta opção está ativada, o Tutor de IA não é apresentado nas páginas que mostram uma atividade de exame do Raison, para que os alunos não lhe possam pedir ajuda durante uma prova. A atividade é reconhecida porque foi criada pelo Raison ou porque a ferramenta externa que inicia utiliza o anfitrião definido em "Anfitrião da ferramenta LTI do Raison" abaixo. As restantes atividades Ferramenta externa não são afetadas: para essas, utilize "Atividades excluídas" acima. Mantenha esta opção ativada, a menos que pretenda deliberadamente que o Tutor de IA esteja disponível durante os exames do Raison.';
$string['installfailed'] = 'O plugin Raison não conseguiu concluir a instalação: {$a}';
$string['installtroubleshoot'] = 'Se encontrar algum problema durante a instalação, consulte o <a href="https://troubleshoot-moodle.raison.is" target="_blank">guia de resolução de problemas </a>.';
$string['invalidredirecturl'] = 'O Raison devolveu um destino de redirecionamento não fidedigno.';
$string['legacycredentialmigrationblockednotice'] = 'As credenciais herdadas do Raison ainda não foram substituídas, porque nenhum administrador do site está, neste momento, em condições de assumir a integração. Atribua a capacidade "moodle/site:config" a um administrador ativo; o Raison tenta novamente a cada hora, de forma automática.';
$string['legacycredentialmigrationdeferred'] = 'Não foi possível iniciar a substituição das credenciais herdadas do Raison durante a atualização. A atualização foi concluída, e o Raison tentará novamente a cada hora, de forma automática. Consulte Administração do site > Plugins > Plugins locais > Raison para ver o estado atual.';
$string['legacycredentialmigrationfailed'] = 'Não foi possível substituir de forma verificável as credenciais herdadas do Raison. O Moodle voltará a tentar a migração automaticamente, utilizando o seu executor de tarefas ad hoc.';
$string['legacycredentialmigrationpendingnotice'] = 'As credenciais herdadas de uma versão anterior do plugin estão a ser substituídas. Até o Raison confirmar a substituição, o token de serviço web herdado continua em utilização, e deixará de funcionar dentro de algumas horas, quer a substituição tenha sido concluída quer não. O Moodle tenta novamente de forma automática; se este aviso persistir, verifique em Administração do site > Servidor > Tarefas > Tarefas ad hoc se existe uma tarefa de migração do Raison com falhas.';
$string['localhosterror'] = 'Não é possível registar a instância do Moodle no Raison porque o site está a ser executado em localhost.';
$string['ltitoolhost'] = 'Anfitrião da ferramenta LTI do Raison';
$string['ltitoolhostdesc'] = 'O anfitrião a partir do qual a ferramenta de exames do Raison é iniciada. O Raison disponibiliza uma ferramenta de exames com IA baseada em LTI, que talvez ainda não esteja configurada no seu site: se quiser saber mais ou experimentá-la, contacte a equipa Raison através de contact@raison.is. Caso contrário, mantenha esta definição como está. Tem de corresponder ao campo "URL da ferramenta" da ferramenta externa "Raison AI Exams", e o Raison recusa colocar um exame utilizando qualquer ferramenta que seja iniciada noutro local. Altere-a apenas se o suporte do Raison o pedir, por exemplo após uma mudança de endereço do serviço. Um valor vazio ou inválido faz com que seja utilizado o valor predefinido.';
$string['messageprovider:setuppending'] = 'Lembrete de configuração do Raison';
$string['messageprovider:tokenexpirywarning'] = 'Aviso sobre o token de serviço web do Raison';
$string['missingcapability'] = 'Sem permissão para aceder a esta página';
$string['noapikey'] = 'Nenhuma chave de API do Raison';
$string['placementnotowned'] = 'Esta atividade não foi criada pelo Raison, pelo que o Raison não a pode renomear nem remover.';
$string['placementtoolnotallowed'] = 'Os exames só podem ser colocados utilizando a ferramenta externa do Raison. A ferramenta selecionada é iniciada a partir de "{$a->host}", mas este site aceita apenas "{$a->allowed}". Consulte a definição "Anfitrião da ferramenta LTI do Raison".';
$string['pluginname'] = 'Plugin local Raison';
$string['privacy:apikeymissing'] = 'Este site está registado no Raison, mas não tem chave de API do Raison, pelo que os dados que o Raison detém para este pedido de privacidade não puderam ser exportados nem eliminados. Reponha a chave de API na página de definições do plugin Raison, ou emita uma nova nessa página com «Renovar a chave de API», e volte a executar o pedido.';
$string['privacy:metadata:config_plugins'] = 'O plugin guarda, na sua configuração local, um registo do administrador que consentiu a integração e confirmou a declaração.';
$string['privacy:metadata:config_plugins:serviceaccountid'] = 'O ID da conta de serviço dedicada que é proprietária do token de serviço web do Raison. Esta conta é criada pelo plugin e não representa nenhuma pessoa.';
$string['privacy:metadata:config_plugins:setupconsentedat'] = 'O momento em que o administrador consentiu a ativação da integração com o Raison.';
$string['privacy:metadata:config_plugins:setupconsentedby'] = 'O ID do administrador que consentiu a ativação da integração com o Raison.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedat'] = 'O momento em que o administrador confirmou a declaração de integração.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedby'] = 'O ID do administrador que confirmou a declaração de integração.';
$string['privacy:metadata:config_plugins:webservicetokenownerid'] = 'O ID do utilizador proprietário do token de serviço web atual do Raison. Trata-se da conta de serviço dedicada, exceto durante a transferência a partir de uma versão anterior, em que é brevemente o administrador que configurou a integração.';
$string['privacy:metadata:raison'] = 'Os metadados enviados para o Raison permitem um acesso integrado aos seus dados no sistema remoto.';
$string['privacy:metadata:raison:courseid'] = 'O ID da disciplina que está a ver é enviado para abrir o assistente Raison dessa disciplina.';
$string['privacy:metadata:raison:currentpageurl'] = 'O endereço da página da disciplina que está a ver é enviado juntamente com a sua identidade quando o assistente Raison é carregado nessa página.';
$string['privacy:metadata:raison:interaction'] = 'Registos das suas interações, como os tutores criados e as conversas, são enviados para melhorar a sua experiência.';
$string['privacy:metadata:raison:useremail'] = 'O seu endereço de e-mail é enviado para o identificar de forma inequívoca no Raison e permitir comunicações futuras.';
$string['privacy:metadata:raison:userfirstname'] = 'O seu nome próprio é enviado para personalizar a sua experiência no Raison e identificar as suas conversas para o seu formador.';
$string['privacy:metadata:raison:userid'] = 'O ID do utilizador é enviado para o identificar de forma inequívoca no Raison.';
$string['privacy:metadata:raison:userlastname'] = 'O seu apelido é enviado para personalizar a sua experiência no Raison e identificar as suas conversas para o seu formador.';
$string['privacy:metadata:raison:userrolename'] = 'O nome do seu papel é enviado para gerir as suas permissões no Raison.';
$string['privacy:setupsubcontext'] = 'Configuração da integração com o Raison';
$string['raisontuto'] = 'Saiba como utilizar o Raison consultando <a href="https://troubleshoot-moodle.raison.is" target="_blank">este tutorial</a>.';
$string['redirectingmessage'] = 'Se não for redirecionado automaticamente, clique no botão abaixo para continuar para o Raison.';
$string['reloadpage'] = 'Recarregar a página';
$string['restprotocolenableerror'] = 'Não foi possível ativar o protocolo REST.';
$string['retryregistration'] = 'Tentar novamente o registo no Raison';
$string['retryseparator'] = 'ou';
$string['roledescription'] = 'Papel para gerir os Tutores de IA do Raison';
$string['rolename'] = 'Gestor do Raison';
$string['roleproblem'] = 'Encontrámos um problema ao criar ou atribuir o novo papel Gestor do Raison. Pode ainda configurá-lo manualmente, concedendo a capacidade "Plugin local Raison" a qualquer papel de sistema. Se encontrar algum problema, contacte a equipa Raison através de contact@raison.is.';
$string['serviceaccountdescription'] = 'Conta dedicada, não administradora, utilizada pela integração do Raison para chamar o serviço web do Moodle. Não pode iniciar sessão e detém apenas as capacidades exigidas pela integração. Criada e mantida automaticamente pelo Plugin local Raison.';
$string['serviceaccountfirstname'] = 'Raison';
$string['serviceaccountlastname'] = 'Serviço de integração';
$string['serviceaccountmigrationpendingnotice'] = 'O token de serviço web do Raison está a ser transferido do administrador do site que configurou a integração para uma conta de serviço dedicada, não administradora. Ambos continuam válidos até a transferência estar concluída, pelo que nada deixa de funcionar entretanto. O Moodle conclui a transferência na próxima execução agendada e, em seguida, revoga o token do administrador.';
$string['serviceaccountremovalfailed'] = 'Não foi possível suspender a conta de serviço da integração do Raison durante a desinstalação. Pode suspender ou remover manualmente a conta "{$a->username}" em Administração do site > Utilizadores > Contas. Detalhes: {$a->error}';
$string['serviceaccountroledescription'] = 'Capacidades detidas pela conta de serviço da integração do Raison. Este papel é mantido automaticamente e não se destina a utilizadores interativos.';
$string['serviceaccountrolename'] = 'Serviço de integração do Raison';
$string['servicecreationerror'] = 'Não foi possível criar o serviço REST do Raison.';
$string['setupaction'] = 'Abrir a configuração do Raison';
$string['setupchangeregistration'] = 'Será criado um token de serviço web do Moodle, que será enviado para o Raison para registar este site.';
$string['setupchangerest'] = 'O protocolo REST será adicionado, preservando todos os protocolos já ativados.';
$string['setupchangetokenlifetime'] = 'Por predefinição, o token expirará ao fim de 15 dias e será substituído automaticamente antes de expirar.';
$string['setupchangewebservices'] = 'Os serviços web do Moodle serão ativados, caso estejam atualmente desativados.';
$string['setupconfirmbutton'] = 'Ativar os serviços web e o REST';
$string['setupconfirmquestion'] = 'Consente estas alterações de configuração a nível de todo o site e o início do registo no Raison?';
$string['setupconsentdescription'] = 'O Raison está atualmente inativo. Ativá-lo efetua as seguintes alterações a nível de todo o site:';
$string['setupconsentheading'] = 'Analise e aprove a ativação do Raison';
$string['setupconsentmissing'] = 'O registo no Raison não pode ser executado sem o consentimento registado de um administrador.';
$string['setupcontinuebutton'] = 'Iniciar o registo no Raison';
$string['setupcontinuequestion'] = 'Iniciar o registo no Raison agora?';
$string['setupcurrentstatus'] = 'Estado atual — serviços web do Moodle: {$a->webservices}; REST: {$a->rest}.';
$string['setupdisablerotation'] = 'Não renovar o token de serviço web';
$string['setupdisablerotationdesc'] = 'Deixe esta opção desmarcada, a menos que não seja possível contar aqui com a substituição automática, por exemplo quando o cron do Moodle não é executado regularmente ou quando o acesso de saída ao Raison não está garantido. Se a marcar, o token criado para este site não expirará nem será substituído, e o Raison deixará de verificar periodicamente se este site ainda concede as funções de que a integração necessita. Pode alterar isto mais tarde nas definições do plugin.';
$string['setupdisablerotationforcedoff'] = 'A renovação do token é imposta pela configuração deste servidor e não pode ser desativada aqui. O token criado para este site expirará ao fim de 15 dias e será substituído automaticamente.';
$string['setupdisablerotationforcedon'] = 'A renovação do token está desativada pela configuração deste servidor e não pode ser ativada aqui. O token criado para este site não expirará.';
$string['setuppagetitle'] = 'Configuração do Raison';
$string['setuppendingbody'] = 'O Raison está instalado neste site, mas nenhum administrador iniciou a sua configuração; por isso, o site nunca foi registado no Raison e nenhuma funcionalidade do Raison está disponível. Um administrador do site tem de abrir {$a} para analisar a declaração de integração e iniciar o registo. Este é o resultado habitual de uma instalação por linha de comandos: o pedido para abrir essa página só é apresentado durante a própria instalação, pelo que ninguém o vê depois. O Moodle envia este lembrete no máximo três vezes.';
$string['setuppendingsubject'] = 'O Raison está instalado, mas não está ativo';
$string['setupqueued'] = 'O seu consentimento foi registado e o registo no Raison foi colocado em fila.';
$string['setupqueuedwithoutconsent'] = 'O registo no Raison foi colocado em fila. Nenhuma definição de serviço web do site foi alterada.';
$string['setupreadydescription'] = 'Os serviços web e o REST do Moodle já estão ativados; por isso, não é necessário nenhum consentimento de ativação e estas definições não serão alteradas.';
$string['setupreadyheading'] = 'Os requisitos de serviço web já estão ativados';
$string['setupreadynotification'] = 'O Raison foi instalado. Os serviços web e o REST do Moodle já estão ativados. Um administrador do site tem de <a href="{$a}">analisar a declaração de integração e iniciar o registo</a>.';
$string['setuprequirednotification'] = 'O Raison foi instalado, mas continua inativo. Um administrador do site tem de <a href="{$a}">analisar a declaração de integração e aprovar as alterações necessárias aos serviços web</a>.';
$string['setuprequiredtrainer'] = 'O Raison ainda não foi configurado neste site. Não foi iniciado nenhum registo no Raison, pelo que nada nesta página pode funcionar até um administrador concluir a configuração. É isto que uma instalação por linha de comandos deixa para trás: os ficheiros do plugin estão no lugar, mas a página de configuração nunca foi aberta.';
$string['setuprequiredtrainernoaccess'] = 'O Raison ainda não foi configurado neste site, pelo que não pode ser utilizado. Só um administrador do site pode concluir a configuração: peça a um administrador que abra Administração do site > Plugins > Plugins locais > Raison e a conclua. Nada nesta página funcionará até que isso seja feito.';
$string['setupsettingdisabled'] = 'desativado';
$string['setupsettingenabled'] = 'ativado';
$string['setupstatus'] = 'Estado da integração';
$string['setupstatuscomplete'] = 'Ligado. O consentimento do administrador está registado e o registo no Raison foi concluído.';
$string['setupstatuspending'] = 'O consentimento do administrador está registado e o registo está pendente. {$a}';
$string['setupstatusready'] = 'Os serviços web e o REST já estão ativados. Analise a declaração de integração e, em seguida, inicie o registo sem consentimento de ativação. {$a}';
$string['setupstatusrequired'] = 'A configuração exige a confirmação da declaração de integração e o consentimento do administrador para as alterações aos serviços web. {$a}';
$string['sidepanel'] = 'Posicionamento do Tutor de IA no ecrã';
$string['sidepaneldesc'] = 'Escolha se prefere apresentar os Tutores de IA no lado direito das disciplinas, como um Painel lateral (recomendado), ou no canto inferior direito, como um Chatbot clássico.';
$string['taskrotatewebservicetoken'] = 'Renovar o token de serviço web do Moodle utilizado pelo Raison';
$string['tasksendsetupreminder'] = 'Lembrar os administradores de que a configuração do Raison não está concluída';
$string['tokencreationerror'] = 'Não foi possível criar o token REST do Raison.';
$string['tokenexpiryunknown'] = 'uma data desconhecida';
$string['tokenexpirywarningbody'] = 'O Raison não conseguiu renovar o seu token de serviço web do Moodle. O token atual expira a {$a->expiry}. Código de erro seguro: {$a->error}. Abra as definições do plugin Raison e verifique o cron e a conectividade.';
$string['tokenexpirywarningbodylifetime'] = 'O Raison não conseguiu aplicar a definição atual de renovação do token, pelo que o token de serviço web do Moodle ainda utiliza a sua validade anterior. Código de erro seguro: {$a->error}. Abra as definições do plugin Raison e verifique o cron e a conectividade.';
$string['tokenexpirywarningsubject'] = 'A renovação do token do Raison requer atenção';
$string['tokenmissing'] = 'Não foi possível encontrar o token de serviço web atual do Raison.';
$string['tokenname'] = 'Token REST do Raison';
$string['tokenrotationdisabled'] = 'A renovação do token está desativada. O token de serviço web do Raison não expira e não é substituído automaticamente.';
$string['tokenrotationdisabledpending'] = 'A renovação do token está desativada, mas o token atual ainda mantém a sua data de expiração original. O Moodle aplica a alteração na próxima execução agendada, o que requer o cron e uma chamada bem-sucedida ao Raison.';
$string['tokenrotationrequestfailed'] = 'O pedido de renovação do token do Raison falhou.';
$string['tokenrotationresponseinvalid'] = 'O Raison devolveu uma confirmação de renovação do token inválida.';
$string['tokenrotationretry'] = 'Tentar novamente a renovação do token';
$string['tokenrotationretryconfirm'] = 'Colocar em fila uma nova tentativa imediata utilizando o token candidato e o ID de renovação existentes?';
$string['tokenrotationretryqueued'] = 'A nova tentativa de renovação do token do Raison foi colocada em fila.';
$string['tokenrotationstatusfailed'] = 'A renovação do token ainda não foi bem-sucedida. O token atual expira a {$a->expiry}. Código de erro seguro: {$a->error}. O Moodle voltará a tentar automaticamente. <a href="{$a->retryurl}">Tentar agora</a>.';
$string['tokenrotationstatusfailedlifetime'] = 'A definição de renovação do token ainda não foi aplicada, pelo que o token atual ainda utiliza a validade anterior. Código de erro seguro: {$a->error}. Normalmente, isto significa que o Moodle não conseguiu contactar o Raison ou que o Raison ainda não foi atualizado para aceitar tokens sem expiração. O Moodle voltará a tentar automaticamente. <a href="{$a->retryurl}">Aplicar agora</a>.';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Painel lateral';
$string['unexpectederror'] = 'Ocorreu um erro inesperado. Tente novamente. Se o erro persistir, contacte a equipa Raison.';
$string['uninstallroleremovalfailed'] = 'Não foi possível remover o papel Gestor do Raison durante a desinstalação, e este pode ainda existir em Administração do site > Utilizadores > Permissões > Definir papéis. Pode eliminá-lo manualmente. Detalhes: {$a}';
$string['uninstallserviceroleremovalfailed'] = 'Não foi possível remover o papel Serviço de integração do Raison durante a desinstalação. Continua a deter capacidades ao nível do sistema, pelo que o deve eliminar manualmente em Administração do site > Utilizadores > Permissões > Definir papéis. Detalhes: {$a}';
$string['viewrolescapability'] = 'Permite que os utilizadores obtenham os papéis do Moodle através do serviço web do Raison';
$string['webservicesenableerror'] = 'Não foi possível ativar os serviços web.';
