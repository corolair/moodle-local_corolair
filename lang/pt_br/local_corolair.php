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

$string['adhocqueued'] = 'A sincronização com os serviços do Raison deveria ter começado na sua tarefa ad hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Caso contrário, solicite a geração de uma chave de API <a href="{$a->trainer_page_link}">aqui</a>.';
$string['apikey'] = 'Chave de API do Raison';
$string['apikeydesc'] = 'Esta chave é gerada durante a instalação do plugin. Mantenha-a em sigilo. Ela pode ser solicitada pela equipe de suporte do Raison.';
$string['apikeymissing'] = 'Chave de API não encontrada na resposta da API do Raison.';
$string['apikeyrotate'] = 'Renovar a chave de API';
$string['apikeyrotateconfirm'] = 'Esta ação gera uma nova chave de API do Raison e invalida imediatamente a chave atual. Continuar?';
$string['apikeyrotatefailed'] = 'A renovação da chave de API do Raison falhou. Verifique os serviços web e a conectividade e tente novamente.';
$string['apikeyrotatenotoken'] = 'Nenhum token de serviço web do Raison foi encontrado. Conclua a configuração do plugin antes de renovar a chave de API.';
$string['apikeyrotatesuccess'] = 'Uma nova chave de API do Raison foi gerada. A chave anterior não é mais válida.';
$string['apikeyset'] = 'A chave de API foi definida com sucesso.';
$string['assignmanagerrolecapability'] = 'Atribuir o papel Gerente do Raison por meio da API de integração com escopo restrito.';
$string['cachedef_widgetcourses'] = 'Lista de cursos do Raison: os cursos deste site que têm um assistente Raison, atualizada a cada cinco minutos.';
$string['calendlydemo'] = 'Para que possamos ajudá-lo de forma eficaz, descreva primeiro o seu caso de uso e as suas necessidades em uma chamada de descoberta com a equipe Raison. Depois de entendermos os seus requisitos, nossos desenvolvedores darão prioridade à resolução dos problemas de conexão com a sua instância do Moodle. Agende a sua chamada <strong> <a href="https://discoverycall.raison.is/" target="_blank">aqui</a> </strong>.';
$string['capabilityassignerror'] = 'Não foi possível atribuir a capacidade "{$a}" ao papel.';
$string['capabilityfalse'] = 'Falso';
$string['capabilitytrue'] = 'Verdadeiro';
$string['corolair:assignmanagerrole'] = 'Atribuir o papel Gerente do Raison por meio da API de integração com escopo restrito.';
$string['corolair:createtutor'] = 'Permite ao usuário criar e gerenciar tutores no plugin Raison.';
$string['corolair:viewroles'] = 'Permite ao usuário obter os metadados dos papéis do Moodle para a integração com o Raison.';
$string['coursenodetitle'] = 'Assistente de IA Raison';
$string['createtutorcapability'] = 'Permite que os usuários criem e gerenciem Tutores de IA no Raison';
$string['createtutorcapabilitydesc'] = 'O usuário não poderá criar Tutores de IA a partir de cursos que não pode gerenciar. Se definido como Falso, poderá criar Tutores de IA a partir de cursos nos quais está apenas inscrito.';
$string['curlerror'] = 'Ocorreu um erro na comunicação com a API do Raison. Não foi possível registrar a sua instância do Moodle; tente novamente. Se o erro persistir, entre em contato com a equipe Raison.';
$string['deregisterfailed'] = 'O Raison não conseguiu confirmar o cancelamento do registro remoto após as tentativas disponíveis. O acesso ao Moodle foi revogado e as credenciais locais da integração foram removidas, mas a exclusão dos dados já mantidos pelo Raison não foi confirmada tecnicamente. Entre em contato com contact@raison.is, no âmbito do seu contrato de serviço ou de tratamento de dados aplicável, e informe a URL deste site Moodle para concluir o processo de exclusão.';
$string['disabletokenrotation'] = 'Desativar a renovação do token de serviço web';
$string['disabletokenrotationdesc'] = 'Por padrão, o token de serviço web do Raison expira após 15 dias e é substituído automaticamente antes de expirar. Ative esta opção somente se não for possível contar com essa substituição automática, por exemplo quando o cron do Moodle não é executado regularmente ou quando o acesso de saída ao Raison não é garantido. Três consequências: o token deixa de expirar; sempre que esta configuração mudar, o token anterior continuará utilizável por até mais 7 dias, para que as solicitações já em andamento não sejam interrompidas; e o Raison deixa de verificar periodicamente se este site ainda concede as funções de que a integração precisa, de modo que um erro de configuração é detectado mais tarde do que seria de outra forma. A aplicação de uma alteração requer o cron e uma chamada bem-sucedida ao Raison; o andamento é informado acima.';
$string['disclosureaccess'] = 'Acesso';
$string['disclosureaccessread'] = 'Leitura';
$string['disclosureaccesswrite'] = 'Escrita';
$string['disclosureacknowledgebutton'] = 'Confirmo que li esta declaração de integração';
$string['disclosureacknowledgmentnote'] = 'Esta confirmação atesta que você analisou o escopo da integração. Ela não é apresentada como um consentimento jurídico.';
$string['disclosureallowlist'] = 'O token está restrito à lista fixa de funções permitidas do serviço corolair_rest. Ele não pode invocar funções de serviço web do Moodle fora dessa lista.';
$string['disclosurecapabilitiesheading'] = 'Por que as capacidades do Moodle estão envolvidas';
$string['disclosurecapabilitiesintro'] = 'O Moodle avalia as capacidades do proprietário do token, além da lista de funções permitidas do serviço. O token pertence a uma conta de serviço dedicada que não é administradora do site e que possui exatamente as capacidades listadas abaixo, e nenhuma outra. Cada capacidade listada é exigida por uma função específica da lista de funções permitidas acima.';
$string['disclosurecapability'] = 'Capacidade';
$string['disclosurecapcompletion'] = 'Permite leituras de conclusão reservadas a futuras funcionalidades de tutoria e de análise baseadas no progresso. Os dados de conclusão não são processados atualmente.';
$string['disclosurecapcontent'] = 'Permite ler o conteúdo dos tipos de atividade suportados e baixar os arquivos correspondentes.';
$string['disclosurecapcoursevisibility'] = 'Permite ler a estrutura de cursos e categorias, incluindo itens ocultos, como material de origem e contexto organizacional dos tutores. A conta de serviço não está inscrita em nenhum curso: é a capacidade course:view que lhe dá acesso de leitura aos cursos. Ela também lê atividades e seções sujeitas a restrições de acesso, incluindo as configuradas para ficarem totalmente ocultas: o Moodle as omite inteiramente da sua resposta, e uma sincronização de conteúdo não consegue distinguir uma atividade omitida de uma atividade excluída.';
$string['disclosurecapexam'] = 'Permite que o fluxo específico de inserção de exames crie, renomeie e exclua a atividade Ferramenta externa que contém um exame do Raison. Esta é a única parte da integração que escreve em um curso, e ela só pode alcançar as atividades criadas pelo próprio Raison: o plugin registra cada inserção que realiza, e as funções de renomear e de excluir recusam qualquer atividade ausente desse registro. Uma atividade Ferramenta externa adicionada por um professor, ou pertencente a outro produto, fica fora de alcance. A inserção é recusada de imediato, a menos que a ferramenta selecionada seja iniciada a partir do host do Raison configurado.';
$string['disclosurecapidentity'] = 'Permite ler os campos de identidade e de e-mail necessários para a correspondência de contas, os convites, a personalização e a delimitação do acesso. Estas capacidades apenas liberam a leitura de campos: não permitem nenhuma alteração em uma conta de usuário. O Moodle classifica moodle/course:useremail como escrita e a rotula como "Ativar/desativar endereço de e-mail", o que exagera o seu alcance: ela não traz nenhum indicador de risco e concede apenas a possibilidade de ler um endereço que o usuário optou por não exibir. Ela está incluída porque, sem ela, o Moodle omite totalmente o endereço em sites que restringiram os seus campos de identidade de usuário, e o Raison faz a correspondência de contas pelo endereço de e-mail quando não tem um ID de usuário do Moodle armazenado.';
$string['disclosurecapparticipants'] = 'Permite ler os participantes e os conteúdos de todos os grupos, para que o acesso possa ser delimitado corretamente.';
$string['disclosurecapprotocol'] = 'Permite que o token chame o serviço web REST do Moodle. Sem ela, todas as solicitações são recusadas antes que qualquer função ou capacidade seja considerada.';
$string['disclosurecaproleassign'] = 'Permite ler os papéis do Moodle e atribuir o papel Gerente do Raison a um instrutor convidado a partir do Raison. Ela só pode atribuir esse único papel, que por sua vez não concede nada além deste plugin.';
$string['disclosuredataaccess'] = 'Inscrições, papéis, grupos e relações entre participantes são usados para determinar quem pode acessar cada tutor.';
$string['disclosuredatacompletion'] = 'A conclusão de atividades e de cursos está reservada a uma funcionalidade futura baseada no progresso e não é processada atualmente.';
$string['disclosuredatacourse'] = 'Categorias de cursos, estrutura, seções, recursos, lições, pacotes SCORM e H5P, vídeos UbiCast e metadados LTI são usados como material de origem dos tutores e como contexto organizacional.';
$string['disclosuredataexam'] = 'Quando a funcionalidade de inserção de exames é acionada, as informações da atividade LTI e da inserção são usadas para criar, atualizar ou remover a inserção solicitada.';
$string['disclosuredataheading'] = 'Uso de dados pessoais e de aprendizagem';
$string['disclosuredataidentity'] = 'IDs, nomes, e-mails e papéis de administradores e usuários permitem o provisionamento de contas, os convites, a correspondência de identidades, a personalização e o acesso às sessões.';
$string['disclosuredatasite'] = 'A URL do site Moodle, o nome do site e a identidade do serviço registram e identificam esta instalação.';
$string['disclosurefiletransfer'] = 'O download de arquivos está ativado para este serviço, para que os recursos de curso suportados possam ser lidos. O upload de arquivos está desativado: a integração nunca grava arquivos no Moodle, e o Moodle controla os uploads apenas por essa opção do serviço, e não pela lista de funções permitidas.';
$string['disclosurefunction'] = 'Função de serviço web';
$string['disclosurefunctionsheading'] = 'Lista fixa de funções de serviço web permitidas';
$string['disclosurefunctionsintro'] = 'O plugin padronizado expõe as 28 funções a seguir. Expanda cada grupo para ver os nomes exatos e a classificação de leitura/escrita.';
$string['disclosuregroupcompletion'] = 'Leituras de conclusão';
$string['disclosuregroupcompletiondesc'] = 'Ler a conclusão de atividades e de cursos para uma futura tutoria baseada no progresso. Esses dados não são processados atualmente.';
$string['disclosuregroupcontent'] = 'Leituras de cursos e de conteúdos de aprendizagem';
$string['disclosuregroupcontentdesc'] = 'Ler catálogos de cursos, seções, recursos, lições, pacotes SCORM e H5P, vídeos UbiCast e metadados LTI usados para criar e organizar os tutores.';
$string['disclosuregroupenrolment'] = 'Leituras de inscrições, participantes e papéis';
$string['disclosuregroupenrolmentdesc'] = 'Ler as inscrições nos cursos, os participantes, as capacidades e os papéis do Moodle usados para delimitar o acesso aos tutores.';
$string['disclosuregroupexamplacement'] = 'Inserção de exames e escrita de atividades';
$string['disclosuregroupexamplacementdesc'] = 'Escritas específicas, usadas apenas quando um administrador aciona o fluxo de inserção de exames.';
$string['disclosuregroupidentity'] = 'Leituras do site e de identidade';
$string['disclosuregroupidentitydesc'] = 'Ler a identidade do site Moodle e os campos de perfil de usuário necessários para o registro, a correspondência de contas e a personalização.';
$string['disclosuregrouproleassignment'] = 'Escrita de atribuição do papel de instrutor';
$string['disclosuregrouproleassignmentdesc'] = 'Atribuir o papel Gerente do Raison quando um convite autorizado de instrutor é iniciado a partir do Raison.';
$string['disclosureheading'] = 'Analise a declaração de integração do Raison';
$string['disclosureintro'] = 'Antes que qualquer configuração de serviço web seja ativada ou que um token seja criado, analise abaixo o limite exato de acesso, a lista de funções e as finalidades dos dados.';
$string['disclosuremissing'] = 'A declaração de integração atual deve ser confirmada pelo administrador que inicia a configuração.';
$string['disclosureopensource'] = 'O código-fonte do plugin está disponível publicamente para revisão de segurança:';
$string['disclosureplanned'] = 'Uso previsto';
$string['disclosureposttrialagreements'] = 'Se a sua organização optar por continuar usando o Raison após o período de teste gratuito, a continuidade do serviço exigirá acordos formais entre o Raison e a sua organização. Esses acordos definirão as responsabilidades de cada parte e garantirão o alinhamento quanto à proteção de dados, à privacidade, à segurança e aos requisitos regulatórios aplicáveis.';
$string['disclosureprivacycontact'] = 'Para dúvidas sobre o tratamento externo, a retenção ou a exclusão de dados, entre em contato com contact@raison.is.';
$string['disclosurepurpose'] = 'Finalidade';
$string['disclosurerole'] = 'O papel Gerente do Raison é independente do token. Ele concede local/corolair:createtutor e local/corolair:viewroles a usuários interativos do Moodle.';
$string['disclosuresecurityheading'] = 'Propriedade do token e limite de segurança';
$string['disclosureserviceaccountbody'] = 'O token pertence a uma conta dedicada do Moodle chamada "{$a}", criada e mantida por este plugin. Ela não é administradora do site, não tem senha, não pode fazer login de forma interativa e possui apenas as capacidades listadas abaixo. A desinstalação do plugin suspende a conta e exclui o seu papel; a conta em si é mantida, sem nenhum dado pessoal, para que uma reinstalação não acumule duplicatas.';
$string['disclosureserviceaccountcontact'] = 'A conta está registrada com o endereço {$a} para que qualquer pessoa que a encontre entre os seus usuários saiba a quem ela pertence e a quem perguntar sobre ela. O Moodle nunca envia e-mails para ela: a conta é criada com as notificações por e-mail desativadas e com o endereço oculto para os demais usuários. Se esse endereço já pertencer a uma conta real neste site, a conta de serviço recebe um endereço não roteável, para que a conta real não seja afetada.';
$string['disclosureserviceaccountheading'] = 'A conta de serviço dedicada';
$string['disclosurestandardised'] = 'O conjunto de funções do serviço é padronizado e idêntico em todas as instalações suportadas; ele não é ampliado dinamicamente para clientes individuais.';
$string['disclosuretokenowner'] = 'O token de serviço web pertence a uma conta de serviço dedicada, não administradora, e o serviço é restrito para que nenhuma outra conta possa usá-lo. O Moodle avalia cada chamada com as capacidades dessa conta, listadas abaixo.';
$string['disclosuretokenrotationdisabled'] = 'Este site desativou a renovação do token. O token não expira, e o Raison não verifica mais periodicamente se este site ainda concede as funções listadas abaixo.';
$string['disclosuretokentransfer'] = 'O token é transferido ao Raison via HTTPS para que a integração possa chamar as funções permitidas do Moodle. Por padrão, ele expira após 15 dias e é renovado antes de expirar; um administrador do site pode desativar a renovação e, nesse caso, o token não expira.';
$string['disclosureuninstall'] = 'Na desinstalação, o plugin tenta cancelar o registro remoto até três vezes antes de revogar o acesso ao serviço web do Moodle e remover as credenciais locais da integração. Se a exclusão remota não puder ser confirmada, os dados transferidos anteriormente continuam sujeitos ao contrato de serviço ou de tratamento de dados aplicável, e o administrador deve entrar em contato com contact@raison.is, informando a URL do site Moodle, para concluir o processo de exclusão.';
$string['disclosureversion'] = 'Versão da declaração';
$string['errortoken'] = 'Erro ao obter o token';
$string['eventintegrationdisclosureacknowledged'] = 'Declaração de integração confirmada';
$string['eventprivacydeletioncompleted'] = 'Exclusão de privacidade do Raison concluída';
$string['eventremoterequestcompleted'] = 'Solicitação remota ao Raison concluída';
$string['eventwebservicetokenlifecycle'] = 'Ciclo de vida do token de serviço web do Raison atualizado';
$string['excludedmods'] = 'Atividades excluídas';
$string['excludedmodsdesc'] = 'Use esta lista para desativar os assistentes em tipos de atividade específicos, por exemplo para impedir que os estudantes os utilizem durante as avaliações. Informe uma lista separada por vírgulas de nomes curtos de módulos de atividade (por exemplo, "quiz, assign"). O nome curto é a pasta exibida na URL da atividade após \'/mod/\' (por exemplo, \'/mod/quiz/\' → \'quiz\'). Isso também funciona para módulos de atividade fornecidos por plugins externos.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['hideonraisonexam'] = 'Ocultar o Tutor de IA durante os exames do Raison';
$string['hideonraisonexamdesc'] = 'O Raison oferece uma ferramenta de exames com IA baseada em LTI, que talvez ainda não esteja configurada no seu site: se quiser saber mais ou experimentá-la, entre em contato com a equipe Raison em contact@raison.is. Quando esta opção está ativada, o Tutor de IA não é exibido nas páginas que apresentam uma atividade de exame do Raison, para que os estudantes não possam pedir ajuda a ele durante uma prova. A atividade é reconhecida porque foi criada pelo Raison ou porque a ferramenta externa que ela inicia usa o host definido em "Host da ferramenta LTI do Raison" abaixo. As demais atividades Ferramenta externa não são afetadas: para elas, use "Atividades excluídas" acima. Mantenha esta opção ativada, a menos que queira deliberadamente que o Tutor de IA esteja disponível durante os exames do Raison.';
$string['installfailed'] = 'O plugin Raison não conseguiu concluir a instalação: {$a}';
$string['installtroubleshoot'] = 'Se encontrar algum problema durante a instalação, consulte o <a href="https://troubleshoot-moodle.raison.is" target="_blank">guia de solução de problemas </a>.';
$string['invalidredirecturl'] = 'O Raison retornou um destino de redirecionamento não confiável.';
$string['legacycredentialmigrationblockednotice'] = 'As credenciais herdadas do Raison ainda não foram substituídas, pois nenhum administrador do site tem condições, no momento, de assumir a integração. Atribua a capacidade "moodle/site:config" a um administrador ativo; o Raison tenta novamente a cada hora, de forma automática.';
$string['legacycredentialmigrationdeferred'] = 'A substituição das credenciais herdadas do Raison não pôde ser iniciada durante a atualização. A atualização foi concluída, e o Raison tentará novamente a cada hora, de forma automática. Consulte Administração do site > Plugins > Plugins locais > Raison para ver o status atual.';
$string['legacycredentialmigrationfailed'] = 'Não foi possível substituir de forma verificável as credenciais herdadas do Raison. O Moodle tentará a migração novamente, de forma automática, usando o seu executor de tarefas ad hoc.';
$string['legacycredentialmigrationpendingnotice'] = 'As credenciais herdadas de uma versão anterior do plugin estão sendo substituídas. Até que o Raison confirme a substituição, o token de serviço web herdado continua em uso, e ele deixará de funcionar dentro de algumas horas, quer a substituição tenha sido concluída ou não. O Moodle tenta novamente de forma automática; se este aviso persistir, verifique em Administração do site > Servidor > Tarefas > Tarefas ad hoc se há uma tarefa de migração do Raison com falha.';
$string['localhosterror'] = 'Não é possível registrar a instância do Moodle no Raison porque o site está sendo executado em localhost.';
$string['ltitoolhost'] = 'Host da ferramenta LTI do Raison';
$string['ltitoolhostdesc'] = 'O host a partir do qual a ferramenta de exames do Raison é iniciada. O Raison oferece uma ferramenta de exames com IA baseada em LTI, que talvez ainda não esteja configurada no seu site: se quiser saber mais ou experimentá-la, entre em contato com a equipe Raison em contact@raison.is. Caso contrário, mantenha esta configuração como está. Ela deve corresponder ao campo "URL da ferramenta" da ferramenta externa "Raison AI Exams", e o Raison se recusa a inserir um exame usando qualquer ferramenta que seja iniciada em outro lugar. Altere-a somente se o suporte do Raison solicitar, por exemplo após uma mudança de endereço do serviço. Um valor vazio ou inválido faz com que o padrão fornecido seja usado.';
$string['messageprovider:setuppending'] = 'Lembrete de configuração do Raison';
$string['messageprovider:tokenexpirywarning'] = 'Aviso sobre o token de serviço web do Raison';
$string['missingcapability'] = 'Sem permissão para acessar esta página';
$string['noapikey'] = 'Nenhuma chave de API do Raison';
$string['placementnotowned'] = 'Esta atividade não foi criada pelo Raison, portanto o Raison não pode renomeá-la nem removê-la.';
$string['placementtoolnotallowed'] = 'Os exames só podem ser inseridos usando a ferramenta externa do Raison. A ferramenta selecionada é iniciada a partir de "{$a->host}", mas este site aceita apenas "{$a->allowed}". Veja a configuração "Host da ferramenta LTI do Raison".';
$string['pluginname'] = 'Plugin local Raison';
$string['privacy:metadata:config_plugins'] = 'O plugin armazena, na sua configuração local, um registro do administrador que consentiu com a integração e confirmou a declaração.';
$string['privacy:metadata:config_plugins:serviceaccountid'] = 'O ID da conta de serviço dedicada que é proprietária do token de serviço web do Raison. Essa conta é criada pelo plugin e não representa nenhuma pessoa.';
$string['privacy:metadata:config_plugins:setupconsentedat'] = 'O momento em que o administrador consentiu com a ativação da integração com o Raison.';
$string['privacy:metadata:config_plugins:setupconsentedby'] = 'O ID do administrador que consentiu com a ativação da integração com o Raison.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedat'] = 'O momento em que o administrador confirmou a declaração de integração.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedby'] = 'O ID do administrador que confirmou a declaração de integração.';
$string['privacy:metadata:config_plugins:webservicetokenownerid'] = 'O ID do usuário proprietário do token de serviço web atual do Raison. Trata-se da conta de serviço dedicada, exceto durante a transferência a partir de uma versão anterior, quando é brevemente o administrador que configurou a integração.';
$string['privacy:metadata:raison'] = 'Os metadados enviados ao Raison permitem um acesso integrado aos seus dados no sistema remoto.';
$string['privacy:metadata:raison:courseid'] = 'O ID do curso que você está visualizando é enviado para abrir o assistente Raison desse curso.';
$string['privacy:metadata:raison:currentpageurl'] = 'O endereço da página do curso que você está visualizando é enviado com a sua identidade quando o assistente Raison é carregado nela.';
$string['privacy:metadata:raison:interaction'] = 'Registros das suas interações, como os tutores criados e as conversas, são enviados para aprimorar a sua experiência.';
$string['privacy:metadata:raison:useremail'] = 'O seu endereço de e-mail é enviado para identificá-lo de forma única no Raison e permitir comunicações futuras.';
$string['privacy:metadata:raison:userfirstname'] = 'O seu nome é enviado para personalizar a sua experiência no Raison e identificar as suas conversas para o seu instrutor.';
$string['privacy:metadata:raison:userid'] = 'O ID do usuário é enviado para identificá-lo de forma única no Raison.';
$string['privacy:metadata:raison:userlastname'] = 'O seu sobrenome é enviado para personalizar a sua experiência no Raison e identificar as suas conversas para o seu instrutor.';
$string['privacy:metadata:raison:userrolename'] = 'O nome do seu papel é enviado para gerenciar as suas permissões no Raison.';
$string['privacy:setupsubcontext'] = 'Configuração da integração com o Raison';
$string['raisontuto'] = 'Saiba como usar o Raison consultando <a href="https://troubleshoot-moodle.raison.is" target="_blank">este tutorial</a>.';
$string['redirectingmessage'] = 'Se você não for redirecionado automaticamente, clique no botão abaixo para continuar para o Raison.';
$string['reloadpage'] = 'Recarregar a página';
$string['restprotocolenableerror'] = 'Não foi possível ativar o protocolo REST.';
$string['retryregistration'] = 'Tentar novamente o registro no Raison';
$string['retryseparator'] = 'ou';
$string['roledescription'] = 'Papel para gerenciar os Tutores de IA do Raison';
$string['rolename'] = 'Gerente do Raison';
$string['roleproblem'] = 'Encontramos um problema ao criar ou atribuir o novo papel Gerente do Raison. Você ainda pode configurá-lo manualmente, concedendo a capacidade "Plugin local Raison" a qualquer papel de sistema. Se encontrar algum problema, entre em contato com a equipe Raison pelo e-mail contact@raison.is.';
$string['serviceaccountdescription'] = 'Conta dedicada, não administradora, usada pela integração do Raison para chamar o serviço web do Moodle. Ela não pode fazer login e possui apenas as capacidades exigidas pela integração. Criada e mantida automaticamente pelo Plugin local Raison.';
$string['serviceaccountfirstname'] = 'Raison';
$string['serviceaccountlastname'] = 'Serviço de integração';
$string['serviceaccountmigrationpendingnotice'] = 'O token de serviço web do Raison está sendo transferido do administrador do site que configurou a integração para uma conta de serviço dedicada, não administradora. Ambos continuam válidos até a conclusão da transferência, portanto nada deixa de funcionar nesse meio-tempo. O Moodle conclui a transferência na próxima execução agendada e, em seguida, revoga o token do administrador.';
$string['serviceaccountremovalfailed'] = 'Não foi possível suspender a conta de serviço da integração do Raison durante a desinstalação. Você pode suspender ou remover manualmente a conta "{$a->username}" em Administração do site > Usuários > Contas. Detalhes: {$a->error}';
$string['serviceaccountroledescription'] = 'Capacidades da conta de serviço da integração do Raison. Este papel é mantido automaticamente e não se destina a usuários interativos.';
$string['serviceaccountrolename'] = 'Serviço de integração do Raison';
$string['servicecreationerror'] = 'Não foi possível criar o serviço REST do Raison.';
$string['setupaction'] = 'Abrir a configuração do Raison';
$string['setupchangeregistration'] = 'Um token de serviço web do Moodle será criado e enviado ao Raison para registrar este site.';
$string['setupchangerest'] = 'O protocolo REST será adicionado, preservando todos os protocolos já ativados.';
$string['setupchangetokenlifetime'] = 'Por padrão, o token expirará após 15 dias e será substituído automaticamente antes de expirar.';
$string['setupchangewebservices'] = 'Os serviços web do Moodle serão ativados, caso estejam desativados no momento.';
$string['setupconfirmbutton'] = 'Ativar os serviços web e o REST';
$string['setupconfirmquestion'] = 'Você consente com essas alterações de configuração em todo o site e com o início do registro no Raison?';
$string['setupconsentdescription'] = 'O Raison está inativo no momento. Ativá-lo faz as seguintes alterações em todo o site:';
$string['setupconsentheading'] = 'Analise e aprove a ativação do Raison';
$string['setupconsentmissing'] = 'O registro no Raison não pode ser executado sem o consentimento registrado de um administrador.';
$string['setupcontinuebutton'] = 'Iniciar o registro no Raison';
$string['setupcontinuequestion'] = 'Iniciar o registro no Raison agora?';
$string['setupcurrentstatus'] = 'Status atual — serviços web do Moodle: {$a->webservices}; REST: {$a->rest}.';
$string['setupdisablerotation'] = 'Não renovar o token de serviço web';
$string['setupdisablerotationdesc'] = 'Deixe esta opção desmarcada, a menos que não seja possível contar com a substituição automática aqui, por exemplo quando o cron do Moodle não é executado regularmente ou quando o acesso de saída ao Raison não é garantido. Se marcá-la, o token criado para este site não expirará nem será substituído, e o Raison deixará de verificar periodicamente se este site ainda concede as funções de que a integração precisa. Você pode alterar isso mais tarde nas configurações do plugin.';
$string['setupdisablerotationforcedoff'] = 'A renovação do token é imposta pela configuração deste servidor e não pode ser desativada aqui. O token criado para este site expirará após 15 dias e será substituído automaticamente.';
$string['setupdisablerotationforcedon'] = 'A renovação do token está desativada pela configuração deste servidor e não pode ser ativada aqui. O token criado para este site não expirará.';
$string['setuppagetitle'] = 'Configuração do Raison';
$string['setuppendingbody'] = 'O Raison está instalado neste site, mas nenhum administrador iniciou a sua configuração; por isso, o site nunca foi registrado no Raison e nenhuma funcionalidade do Raison está disponível. Um administrador do site deve abrir {$a} para analisar a declaração de integração e iniciar o registro. Esse é o resultado habitual de uma instalação por linha de comando: o pedido para abrir essa página só é exibido durante a própria instalação, de modo que ninguém o vê depois. O Moodle envia este lembrete no máximo três vezes.';
$string['setuppendingsubject'] = 'O Raison está instalado, mas não está ativo';
$string['setupqueued'] = 'O seu consentimento foi registrado e o registro no Raison foi colocado na fila.';
$string['setupqueuedwithoutconsent'] = 'O registro no Raison foi colocado na fila. Nenhuma configuração de serviço web do site foi alterada.';
$string['setupreadydescription'] = 'Os serviços web e o REST do Moodle já estão ativados; portanto, nenhum consentimento de ativação é necessário e essas configurações não serão alteradas.';
$string['setupreadyheading'] = 'Os requisitos de serviço web já estão ativados';
$string['setupreadynotification'] = 'O Raison foi instalado. Os serviços web e o REST do Moodle já estão ativados. Um administrador do site deve <a href="{$a}">analisar a declaração de integração e iniciar o registro</a>.';
$string['setuprequirednotification'] = 'O Raison foi instalado, mas permanece inativo. Um administrador do site deve <a href="{$a}">analisar a declaração de integração e aprovar as alterações necessárias nos serviços web</a>.';
$string['setuprequiredtrainer'] = 'O Raison ainda não foi configurado neste site. Nenhum registro no Raison foi iniciado, portanto nada nesta página pode funcionar até que um administrador conclua a configuração. É isso que uma instalação por linha de comando deixa para trás: os arquivos do plugin estão no lugar, mas a página de configuração nunca foi aberta.';
$string['setuprequiredtrainernoaccess'] = 'O Raison ainda não foi configurado neste site, por isso não pode ser usado. Somente um administrador do site pode concluir a configuração: peça a um administrador que abra Administração do site > Plugins > Plugins locais > Raison e a conclua. Nada nesta página funcionará até que isso seja feito.';
$string['setupsettingdisabled'] = 'desativado';
$string['setupsettingenabled'] = 'ativado';
$string['setupstatus'] = 'Status da integração';
$string['setupstatuscomplete'] = 'Conectado. O consentimento do administrador está registrado e o registro no Raison foi concluído.';
$string['setupstatuspending'] = 'O consentimento do administrador está registrado e o registro está pendente. {$a}';
$string['setupstatusready'] = 'Os serviços web e o REST já estão ativados. Analise a declaração de integração e, em seguida, inicie o registro sem consentimento de ativação. {$a}';
$string['setupstatusrequired'] = 'A configuração exige a confirmação da declaração de integração e o consentimento do administrador para as alterações nos serviços web. {$a}';
$string['sidepanel'] = 'Posicionamento do Tutor de IA na tela';
$string['sidepaneldesc'] = 'Escolha se prefere exibir os Tutores de IA no lado direito dos cursos, como um Painel lateral (recomendado), ou no canto inferior direito, como um Chatbot clássico.';
$string['taskrotatewebservicetoken'] = 'Renovar o token de serviço web do Moodle usado pelo Raison';
$string['tasksendsetupreminder'] = 'Lembrar os administradores de que a configuração do Raison não foi concluída';
$string['tokencreationerror'] = 'Não foi possível criar o token REST do Raison.';
$string['tokenexpiryunknown'] = 'uma data desconhecida';
$string['tokenexpirywarningbody'] = 'O Raison não conseguiu renovar o seu token de serviço web do Moodle. O token atual expira em {$a->expiry}. Código de erro seguro: {$a->error}. Abra as configurações do plugin Raison e verifique o cron e a conectividade.';
$string['tokenexpirywarningbodylifetime'] = 'O Raison não conseguiu aplicar a configuração atual de renovação do token, portanto o token de serviço web do Moodle ainda usa a sua validade anterior. Código de erro seguro: {$a->error}. Abra as configurações do plugin Raison e verifique o cron e a conectividade.';
$string['tokenexpirywarningsubject'] = 'A renovação do token do Raison requer atenção';
$string['tokenmissing'] = 'O token de serviço web atual do Raison não foi encontrado.';
$string['tokenname'] = 'Token REST do Raison';
$string['tokenrotationdisabled'] = 'A renovação do token está desativada. O token de serviço web do Raison não expira e não é substituído automaticamente.';
$string['tokenrotationdisabledpending'] = 'A renovação do token está desativada, mas o token atual ainda mantém a sua data de expiração original. O Moodle aplica a alteração na próxima execução agendada, o que requer o cron e uma chamada bem-sucedida ao Raison.';
$string['tokenrotationrequestfailed'] = 'A solicitação de renovação do token do Raison falhou.';
$string['tokenrotationresponseinvalid'] = 'O Raison retornou uma confirmação de renovação do token inválida.';
$string['tokenrotationretry'] = 'Tentar novamente a renovação do token';
$string['tokenrotationretryconfirm'] = 'Colocar na fila uma nova tentativa imediata usando o token candidato e o ID de renovação existentes?';
$string['tokenrotationretryqueued'] = 'A nova tentativa de renovação do token do Raison foi colocada na fila.';
$string['tokenrotationstatusfailed'] = 'A renovação do token ainda não foi bem-sucedida. O token atual expira em {$a->expiry}. Código de erro seguro: {$a->error}. O Moodle tentará novamente de forma automática. <a href="{$a->retryurl}">Tentar agora</a>.';
$string['tokenrotationstatusfailedlifetime'] = 'A configuração de renovação do token ainda não foi aplicada, portanto o token atual ainda usa a validade anterior. Código de erro seguro: {$a->error}. Isso geralmente significa que o Moodle não conseguiu acessar o Raison ou que o Raison ainda não foi atualizado para aceitar tokens sem expiração. O Moodle tentará novamente de forma automática. <a href="{$a->retryurl}">Aplicar agora</a>.';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Painel lateral';
$string['unexpectederror'] = 'Ocorreu um erro inesperado. Tente novamente. Se o erro persistir, entre em contato com a equipe Raison.';
$string['uninstallroleremovalfailed'] = 'O papel Gerente do Raison não pôde ser removido durante a desinstalação e pode ainda existir em Administração do site > Usuários > Permissões > Definir papéis. Você pode excluí-lo manualmente. Detalhes: {$a}';
$string['uninstallserviceroleremovalfailed'] = 'O papel Serviço de integração do Raison não pôde ser removido durante a desinstalação. Ele ainda possui capacidades no nível do sistema, por isso você deve excluí-lo manualmente em Administração do site > Usuários > Permissões > Definir papéis. Detalhes: {$a}';
$string['viewrolescapability'] = 'Permite que os usuários obtenham os papéis do Moodle por meio do serviço web do Raison';
$string['webservicesenableerror'] = 'Não foi possível ativar os serviços web.';
