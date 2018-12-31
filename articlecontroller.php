<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArtikelController extends Controller
{
    public $nfakta = 0;
    public $nhoax = 0;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $hasil = "";
        $category = "";
        $nfakta = 0;
        $nhoax = 0;
        return view('artikel', compact('hasil', 'category', 'nfakta', 'nhoax'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $raw = $request->data;
        $masuk = strip_tags($raw);

        $category = $this->classify($masuk);

        $sql = DB::table('artikel')->insert(
            ['artikel' => $masuk, 'category' => $category]
        );

        $hasil = $masuk;

        $nfakta = $this->nfakta;
        $nhoax = $this->nhoax;

        echo '<script language="javascript">';
        echo 'alert("Artikel Telah Selesai di Identifikasi")';
        echo '</script>';

        return view('artikel', compact('hasil', 'category', 'nfakta', 'nhoax'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Thread $thread)
    {

    }

    public function classify($sentence) 
    {

            //extract keyword dari dalam text/setence
      $keywordsArray = $this -> tokenize($sentence);

            // mengklasifikasikan kategori
      $category = $this -> decide($keywordsArray);

      return $category;
  }

  private function tokenize($sentence) {
            //758 kata dari: https://github.com/masdevid/ID-Stopwords/blob/master/id.stopwords.02.01.2016.txt

    $stopWords = array();
    $words = DB::table('stopwords')->select('word_sw')->get();

    foreach ($words as $word) {
        array_push($stopWords,$word->word_sw);
    }
    

          // $stopWords = mysqli_fetch_row($sql);

            // $stopWords = array('bagaimanakah','bagaimanapun','bagi','bagian','bahkan','bahwa','bahwasanya','baik','bakal','bakalan','balik','banyak','bapak','baru','bawah','beberapa','begini','beginian','beginikah','beginilah','begitu','begitukah');

            //remove semua karakter alay, angka atau space
    $sentence = preg_replace("/[^a-zA-Z 0-9]+/", "", $sentence);

        //huruf kecil semua
    $sentence = strtolower($sentence);

          //kosong
    $keywordsArray = array();

        //jadiin text nya ke aray
          // dari sene: http://www.w3schools.com/php/func_string_strtok.asp
    $token =  strtok($sentence, " ");
    while ($token !== false) {

          //mengeluarkan element yang panjangnya kurang dari 3
     if (!(strlen($token) <= 2)) {

            //mengeluarkan element yang di masuk di stopwords array
                  //nyari disene: http://www.w3schools.com/php/func_array_in_array.asp
      if (!(in_array($token, $stopWords))) {
       array_push($keywordsArray, $token);
   }
}
$token = strtok(" ");
}
return $keywordsArray;
}


private function decide ($keywordsArray) {
    $hoax = "hoax";
    $fakta = "fakta";
    $netral = "netral";
    $i = 0;
    $nnetral = 0; //Gk ada di fakta dan hoax

            // Defaultnya kita buat fakta aja biar gak sujon
    $category = $fakta;

    $hoaxCount = DB::table('trainingSet')
    ->select(DB::raw('count(*) as total'))
    ->where('category', '=', $hoax)
    ->first();
    $hoaxCount = $hoaxCount->total;


    $faktaCount = DB::table('trainingSet')
    ->select(DB::raw('count(*) as total'))
    ->where('category', '=', $fakta)
    ->first();
    $faktaCount = $faktaCount->total;

    $totalCount = DB::table('trainingSet')
    ->select(DB::raw('count(*) as total'))
    ->first();
    $totalCount = $totalCount->total;
// $this->test = $totalCount;
            //p(hoax) probabilitas dari hoax
            $pHoax = $hoaxCount / $totalCount; // (jumlah dari dokumen yang diklasifikasikan sbg hoax / total documents)

            //p(fakta) probabilitas dari fakta
            $pFakta = $faktaCount / $totalCount; // (jumlah dari dokumen yang diklasifikan sbg fakta / total documents)

            //echo $pHoax." ".$pFakta;
            // jumlah dari distinct kata
            $distinctWords = DB::table('wordFrequency')
            ->select(DB::raw('count(*) as total'))
            ->first();
            $distinctWords = $distinctWords->total;


            $bodyTextIsHoax = ($pHoax); // log
            foreach ($keywordsArray as $word) {
                $wordCount = DB::table('wordFrequency')
                ->select(DB::raw('count as total'))
                ->where([
                    ['word', '=', $word],
                    ['category', '=', $hoax],
                ])
                ->first();
                if (empty($wordCount))
                {
                    $wordCount = 0;
                    $i++;
                }
                else{
                    $wordCount = $wordCount->total;
                    
                }

                if($i == count($keywordsArray))
                  $nnetral++;

              $bodyTextIsHoax += (($wordCount + 1) / ($hoaxCount + $distinctWords)); //log
                // $this->test = $hoaxCount." dan ".$distinctWords." dan ".$wordCount;
          }

          $i = 0;
          $bodyTextIsFakta = ($pFakta); //log
          foreach ($keywordsArray as $word) {
            $wordCount = DB::table('wordFrequency')
            ->select(DB::raw('count as total'))
            ->where([
                ['word', '=', $word],
                ['category', '=', $fakta],
            ])
            ->first();
            if (empty($wordCount))
            {
                $wordCount = 0;
                $i++;
            }
            else
            {
                $wordCount = $wordCount->total;
            }

            if($i == count($keywordsArray) && $nnetral == 1)
            {
                $this->nfakta = $bodyTextIsFakta;
                $this->nhoax = $bodyTextIsHoax;
                return $netral;
            }
            

// $this->test = $faktaCount ." dan ". $distinctWords." dan ".$wordCount;
            $bodyTextIsFakta += (($wordCount + 1) / ($faktaCount + $distinctWords)); //log

        }
      //Mengambil Nilai Fakta dan Hoax
        $this->nfakta = $bodyTextIsFakta;
        $this->nhoax = $bodyTextIsHoax;


        $total = $bodyTextIsFakta + $bodyTextIsHoax;
      // $this->test = $total;
        if ($bodyTextIsFakta >= $bodyTextIsHoax) {
          $category = $fakta;
             // $category = $fakta." ".$bodyTextIsFakta/$total * 100 ."% ".$fakta." ".$bodyTextIsHoax/$total * 100 ."% Fakta".$bodyTextIsFakta." Hoax".$bodyTextIsHoax;
      } 
      elseif ($bodyTextIsFakta == $bodyTextIsHoax) {
        $category = "Netral";
    }
    else {
       $category = $hoax;
   }


   return $category;
}
}
