<?php

require_once __DIR__ . '/bootstrap.php';

connect(default: true);

use Illuminate\Database\Capsule\Manager as DB;

$sourceFile = $argv[1] ?? null;
$rowNum = $argv[2] ?? 0;
$consoleOutput = new \Symfony\Component\Console\Output\ConsoleOutput();

$previouslyFixed = [
    21621765,22258495,22754085,22757415,22765305,22766455,22768755,22902765,22916405,23249545,23252695,
    23255955,23258115,23260435,23265665,23404675,23411885,23411975,23414055,23414085,23543875,23684885,
    23768155,23768735,23770345,23778145,23778755,23779735,23780665,23782475,23877045,23877535,23877875,
    23886365,23888215,23888765,23889255,23892655,23892715,23896355,23902415,23911945,23912395,23913475,
    24390815,24391055,24576265,24599735,24605505,24607095,24754195,24805035,24968815,24971375,25087175,
    25090095,25296655,25460555,25470425,25516705,25616265,25617825,25794905,25923345,26170625,26318145,
    26327775,26473875,26866735,26877565,27284785,27945945,28182325,28279595,28281285,28450515,28686515,
    28704815,28971405,28971665,28971985,28979475,28989605,29006275,29037665,29038305,30084045,30084445,
    30101075,30351015,30456645,30526845,30527655,30652335,30757285,30758055,30902275,30908185,31135935,
    31313335,31316015,31493055,31493745,31577595,31581345,31708235,31708575,31709005,31712035,31717465,
    31914115,31916655,31948095,31948525,31949345,31949665,31949895,31952435,31958785,31959355,31959795,
    31959865,31960315,31960325,31960335,31960445,31960495,31960775,31960875,31961995,31962215,31963435,
    31963685,31963755,31963995,31965135,31965355,31965515,31966555,31966875,31967085,31968835,31969615,
    31969745,31970005,31970765,31970855,31972945,31973805,31975475,31977215,31977665,32081215,32081305,
    32081555,32082385,32082455,32083625,32084015,32084395,32084905,32085115,32085305,32086585,32089405,
    32089555,32090505,32192795,32220445,32221135,32222365,32222815,32222855,32222865,32249545,32251645,
    32255395,32255465,32256115,32256345,32256465,32256485,32256515,32256865,32256945,32257725,32258005,
    32259445,32259745,32260335,32260465,32260525,32260635,32263855,32263885,32265755,32266095,32266655,
    32266785,32267615,32268485,32269495,32270155,32271005,32277755,32279035,32279805,32457595,32458505,
    32458585,32458765,32459245,32462185,32462645,32463105,32463975,32464255,32464305,32472415,32472485,
    32472575,32473745,32476215,32477745,32615725,32615975,32616355,32617705,32617905,32618875,32619295,
    32619365,32619385,32619815,32621955,32621975,32629095,32629175,32629615,32631075,32631095,32834125,
    32875665,32876035,32877845,32878695,32880155,32880575,32887965,32888625,32888995,32889245,32889635,
    32890105,32890625,32891915,32891955,32892225,32892605,32892655,32892765,32892835,32892965,32892995,
    32893065,32893745,32893905,32894105,32895205,32895425,32897475,32897935,32898095,32900765,32900775,
    32902725,33059605,33065775,33066255,33067155,33226755,33227015,33228115,33229595,33230385,33234175,
    33234195,33599615,33599675,33600965,33601985,33602285,33602325,33603025,33603095,33603255,33603995,
    33604315,33604465,33604685,33605465,33605975,33606055,33608495,33608525,33608585,33608725,33609175,
    33611185,33611425,33612135,33612145,33612155,33612365,33612405,33613065,33616755,33618785,33619225,
    33619455,33619735,33620945,33813975,33814025,33815595,33816845,33819855,33820435,33822355,33823385,
    33825935,33829545,33833485,33834205,33835665,33836085,33836135,33837145,33837525,33838745,33839315,
    33840015,33841765,33842125,33842795,33843865,33843965,33844615,33844765,33844835,33848575,33851075,
    33851135,33851625,33854995,33855205,34005995,34109035,34110655,34111195,34112715,34113145,34113925,
    34113975,34114005,34185285,34185295,34185435,34185485,34186625,34187285,34188135,34189075,34192285,
    34222365,34223995,34224055,34226315,34226475,34226945,34227105,34227235,34229725,34231785,34234235,
    34234995,34236235,34236265,34236555,34236815,34236935,34237045,34237255,34237485,34237635,34238425,
    34315695,34315845,34316395,34317495,34318875,34319255,34319955,34319985,34320475,34320825,34322165,
    34322195,34323795,34356915,34357625,34359235,34361475,34366275,34366815,34368155,34368375,34370025,
    34370445,34370565,34456725,34456815,34457005,34457765,34458875,34459325,34459775,34459915,34461235,
    34461745,34461765,34463235,34463365,34465235,34465375,34465445,34466745,34467515,34469995,34471975,
    34472555,34472755,34473365,34475315,34475485,34476725,34477005,34477305,34477615,34477795,34477865,
    34478585,34479375,34480025,34482485,34482515,34482665,34482905,34483295,34483325,34483495,34561865,
    34561945,34563045,34563805,34563835,34563865,34564135,34564475,34566185,34566455,34568735,34571135,
    34571525,34571755,34572265,34573015,34573455,34573975,34574105,34574335,34575155,34575175,34576795,
    34576825,34577075,34577185,34578845,34579535,34579995,34580185,34580975,34581195,34746115,34746165,
    34746695,34747615,34748525,34749025,34749695,34749835,34750235,34750415,34750885,34751055,34751155,
    34751605,34751765,34752175,34752235,34752755,34752845,34754985,34757105,34759405,34783865,34783995,
    34785225,34785405,34785615,34836185,34836345,34836445,34836725,34836855,34836875,34837305,34837675,
    34837805,34837875,34837915,34838165,34838565,34838935,34841105,34841475,34842105,34842345,34843885,
    34843915,34844435,34844835,34845335,34846095,34847215,34849415,34849815,34921115,34921335,34921555,
    34921785,34922225,34922355,34949055,34949245,34949345,34949725,34950875,34951095,34952225,34952745,
    34952925,34954385,34954615,34955635,34955645,34957095,34957915,34958285,34958535,34960475,34961145,
    34961395,34961635,34962025,34962405,34962545,34963065,34969965,34971365,34972785,34974985,34975055,
    34975265,34975355,34975395,34975435,34975445,34975475,34975575,34976555,34977055,34977105,34978085,
    34978995,34979815,34980615,34982325,35118375,35118395,35118885,35119285,35119475,35119495,35120205,
    35120685,35122765,35125775,35126735,35128855,35129025,35129075,35129085,35129635,35129765,35130725,
    35130875,35130995,35131465,35131975,35132845,35133165,35133725,35134645,35136005,35222015,35222085,
    35222195,35223835,35223895,35224225,35224695,35225205,35225395,35225875,35226295,35226665,35226865,
    35228515,35228915,35229035,35229815,35231015,35235995,35237175,35237315,35237965,35238995,35239015,
    35271905,35272315,35272345,35273675,35273765,35274505,35274675,35274865,35275815,35276045,35276075,
    35276555,35276785,35277065,35277655,35278895,35279185,35279385,35279415,35279515,35279665,35279945,
    35281175,35281425,35282115,35282705,35283935,35284795,35287255,35287335,35287645,35287665,35287765,
    35287795,35288235,35288425,35314225,35315675,35317065,35318705,35318755,35319775,35319915,35321125,
    35321635,35324195,35324555,35324965,35326585,35327425,35327695,35330085,35330945,35332395,35332565,
    35333105,35333165,35338485,35338725,35339395,35340775,35341425,35342145,35342175,35342215,35342435,
    35343195,35344295,35344315,35344455,35344565,35346565,35347035,35347065,35347305,35347845,35348315,
    35350095,35350155,35350255,35350575,35350585,35350725,35350785,35350995,35351075,35351415,35351435,
    35351815,35351875,35351895,35352155,35352455,35352645,35352715,35352985,35353265,35353315,35353625,
    35353825,35353855,35354065,35354555,35354625,35354665,35354855,35355475,35355975,35356405,35356515,
    35356725,35356755,35358165,35363765,35364005,35364215,35364245,35364555,35364565,35364595,35365025,
    35365755,35365805,35365895,35366105,35366395,35367175,35367425,35367875,35368075,35368735,35369245,
    35369735,35370035,35370145,35371485,35371835,35371945,35372335,35777695,35777855,35778065,35778735,
    35780505,35781155,35781645,35783135,35783795,35784005,35797835,35797885,35797905,35798315,35798465,
    35798665,35798845,35798885,35815215,35815305,35815385,35815695,35815715,35816755,35817165,35817345,
    35817365,35818815,35818845,35824745,35824965,35825505,35825585,35825735,35825965,35826055,35826115,
    35826705,35828295,35828415,35828525,35829085,35829835,35830115,35830655,35910975,35911115,35911325,
    35911675,35912315,35912665,35913055,35913085,35913385,35913435,35913495,35919135,35920125,35920185,
    35920415,35921015,35922065,35922255,35922475,35922745,35923945,35924645,35924665,35925465,35927215,
    35945095,35949455,35949795,35949835,35949925,35951015,35951105,35951655,35952735,35953075,35953095,
    35953655,35954235,35954985,35955365,36048485,36048695,36049105,36049385,36049735,36049745,36050845,
    36051085,36051165,36051335,36051555,36053525,36053565,36053805,36054755,36057095,36057325,36057405,
    36058155,36059015,36059395,36060635,36066995,36067145,36067205,36068825,36086335,36086655,36087135,
    36087545,36088095,36090305,36211185,36211405,36211985,36212625,36212915,36213655,36214665,36214835,
    36214885,36214945,36215065,36215185,36215565,36215575,36216185,36216705,36217465,36217565,36217785,
    36218035,36218585,36219365,36220555,36264975,36267135,36302135,36302165,36302385,36302585,36302795,
    36303255,36303485,36303495,36303555,36303855,36304115,36304405,36304645,36305825,36306355,36306525,
    36307375,36307765,36308525,36310535,36311495,36312035,36367755,36367995,36368385,36368455,36368745,
    36368935,36369535,36369965,36370315,36370935,36371025,36371075,36371605,36372065,36372485,36372565,
    36375045,36375575,36375645,36434105,36435145,36436655,36437435,36438555,36439075,36440005,36440155,
    36485705,36486115,36486295,36486645,36486845,36487395,36487535,36487595,36488635,36488765,36489295,
    36490215,36516075,36516305,36516345,36516695,36517385,36519235,36538665,36539055,36539395,36539465,
    36539705,36539885,36540375,36541735,36542015,36542085,36543395,36543485,36573805,36575445,36577305,
    36579535,36580655,36580745,36582235,36582445,36583745,36584875,36585545,36585805,36664195,36664635,
    36665035,36665145,36665455,36665595,36665655,36666315,36668045,36669035,36669785,36710355,36712015,
    36712525,36713295,36714085,36714245,36715135,36715465,36715485,36757365,36757925,36784055,36784325,
    36785675,36785805,36804575,36804715,36805205,36805925,36806085,36806285,36828345,36828835,36828955,
    36829385,36829995,36831405,36831495,36831555,36831605,36832085,36832675,36832885,36832975,36833915,
    36834155,36835395,36835545,36835605,36835935,36835955,36836375,36883135,36883425,36884645,36885065,
    36885665,36889295,36891965,36893245,36893975,36894015,36894585,36894785,36894955,36896095,36898085,
    36898695,36898735,36902505,36902565,37028155,37028235,37028465,37028935,37028955,37029655,37029785,
    37030465,37043605,37043875,37064295,37065005,37071955,37071985,37072245,37072705,37073435,37074275,
    37075665,37075965,37076555,37076735,37077095,37077575,37077805,37077915,37165535,37167675,37167725,
    37169025,37169445,37170755,37213685,37214285,37218005,37221445,37222325,37225425,37225645,37226265,
    37307375,37307425,37307495,37307725,37308245,37309015,37309615,37310355,37311235,37311735,37314475,
    37316045,37316615,37318655,37318735,37319155,37319365,37319475,37320035,37321885,37322605,37323265,
    37323335,37326295,37327285,37329595,37330035,37331495,37430575,37430725,37430905,37430975,37433785,
    37433855,37484955,37525165,37525725,37525955,37526185,37526905,37526965,37527925,37528085,37528745,
    37528925,37530085,37530715,37530945,37530995,37531775,37532465,37548745,37548935,37548955,37549055,
    37549165,37549425,37549655,37549665,37549945,37550645,37551255,37552485,37554605,37556645,37556695,
    37559235,37559855,37560205,37677445,37677545,37679715,37680585,37681295,37681465,37711495,37711815,
    37712725,37712775,37712835,37715615,37715845,37716045,37716935,37717555,37720065,37780615,37797625,
    37828265,37862745,37863325,37863435,37863775,37863795,37864055,37865705,37865795,37868105,37868505,
    37868685,37868915,37869245,37871185,37871975,37872395,37872955,37873095,37873135,37873145,37873175,
    37873575,37933905,37935435,37935575,37936775,37937025,37937485,37938045,37943295,37944205,37945105,
    37945275,37945305,37948105,37948215,37948695,37948975,37949095,37949215,37949335,37949485,37949585,
    37949945,37950145,37951035,38052415,38052935,38054435,38054655,38055615,38056575,38056875,38058545,
    38059175,38059645,38060145,38061095,38061135,38145325,38158385,38163515,38165615,38166335,38166365,
    38167135,38168965,38170445,38171125,38173535,38175255,38175805,38284875,38285555,38286895,38305645,
    38306875,38309685,38319145,38319195,38319245,38319275,38319675,38319825,38320035,38320425,38320525,
    38320695,38320765,38320855,38321215,38321445,38321555,38323985,38324105,38324925,38326285,38326945,
    38327365,38327845,38328155,38328285,38341765,38341935,38341945,38342465,38343415,38343645,38506975,
    38557725,38558125,38558325,38558335,38559325,38560535,38561015,38561545,38561685,38562385,38562535,
    38562545,38562855,38563465,38563575,38563965,38564275,38564345,38564815,38564955,38609965,38611435,
    38612055,38612365,38613225,38613525,38614845,38618265,38618775,38618905,38620495,38621275,38622225,
    38622615,38623515,38623725,38623805,38624395,38625295,38627815,38629565,38631135,38633605,38788915,
    38834405
];

if (is_null($sourceFile)) {
    $consoleOutput->writeln('Source file is not specified');
    exit(1);
}

$sourceStream = fopen($sourceFile, 'r');

if (!$sourceStream) {
    $consoleOutput->writeln('Source file is not readable');
    exit(1);
}

$reader = \League\Csv\Reader::createFromStream($sourceStream);
$reader->setHeaderOffset(0);

$countAll = $reader->count();

while ($rowNum <= $countAll) {
    $row = $reader->fetchOne($rowNum);
    if (empty($row)) {
        break;
    }

    $consoleOutput->writeln('');
    $consoleOutput->writeln('Row #' . $rowNum . '/' . $countAll);
    $consoleOutput->writeln('Driver ID: ' . $row['Driver ID']);
    $consoleOutput->writeln('Amount to refund: ' . $row['Amount to refund']);


    $history = new \Symfony\Component\Console\Helper\Table($consoleOutput);
    $history->setHeaders(['id', 'user_id', 'session_id', 'amount', 'account_balance', 'type', 'status', 's_type', 'create_date']);

    $sql = "
    select
        pl.id,
        pl.user_id,
        evc.id session_id,
        pl.amount,
        -- Sign detected based on payment log type:
        -- Log type 2: Driver refunds (see: Coulomb_report.php:544)
        -- Log types 10, 11: Promotion credits (see: Coulomb_report.php:547)
        -- Log type 20: Roaming refund (see: Coulomb_report.php:562)
        -- Log types 1, 4, 6: Account deposit (see: Coulomb_report.573)
        -- if (pl.type in (2, 10, 11, 20, 1, 4, 6), pl.amount, -1 * pl.amount) amount_with_sign, -- The sign identifies if it's income or outcome
        pl.account_balance,
        pl.`type`,
        pl.status,
        ifnull(evce.transaction_type, if (pl.type = 8, 'BUSINESS', '')) s_type,
        pl.create_date
    from clb_user_payment_log pl
    left join clb_external_vehicle_charge evc on evc.id = pl.vc_id
    left join clb_external_vehicle_charge_ext evce on evce.evc_id = evc.id
    where pl.user_id in (?)
    order by pl.user_id, pl.create_date;
    ";

    $rows = DB::connection()->select($sql, [$row['Driver ID']]);

    $sql = "SELECT session_id FROM clb_balance_history WHERE driver_id = ? AND balance_diff < 0 AND is_business = 1";
    $affectedSessions = DB::connection()->select($sql, [$row['Driver ID']]);
    $affectedSessions = array_map(fn ($row) => $row->session_id, $affectedSessions);

    foreach ($rows as $row) {
        $history->addRow(highlight([
            'log_id' => $row->id,
            'user_id' => $row->user_id,
            'session_id' => $row->session_id,
            'amount' => $row->amount,
            'account_balance' => $row->account_balance,
            'type' => $row->type,
            'status' => $row->status,
            's_type' => $row->s_type,
            'create_date' => $row->create_date,
        ], $affectedSessions));
    }

    $history->render();
    readline('Press enter to continue or Ctrl+C to exit');

    $rowNum++;
}

function highlight(array $row, $affectedSessions): array
{
    if (in_array($row['session_id'], $affectedSessions)) {
        return array_map(
            fn ($value) => '<fg=red>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [4,1]) && $row['status'] == 0) {
        return array_map(
            fn ($value) => '<fg=magenta>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [1,4])) {
        return array_map(
            fn ($value) => '<fg=yellow>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [2,5,10,20])) {
        return array_map(
            fn ($value) => '<fg=green>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [8, 19])) {
        return $row;
    }

    return array_map(
        fn ($value) => '<fg=blue>' . $value . '</>',
        $row
    );
}
