<?php

namespace App\Http\Controllers;

use App\Tag;
use App\Thread;
use App\ThreadFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;

class ThreadController extends Controller
{
  public $test = "";
  public $nfakta = 0;
  public $nhoax = 0;

  function __construct()
  {
    return $this->middleware('auth')->except('index');
  }


    /**
     * Display a listing of the resource.
     *
     * @param ThreadFilters $filters
     * @return \Illuminate\Http\Response
     * @internal param Request $request
     */
    public function index(ThreadFilters $filters)
    {
      $threads=Thread::filter($filters)->paginate(10);

      return view('thread.index', compact('threads'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
      return view('thread.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //validate

      $this->validate($request, [
        'subject' => 'required|min:5',
        'tags'    => 'required',
        'thread'  => 'required|min:10',
//            'g-recaptcha-response' => 'required|captcha'
      ]);

        //store
      $thread = auth()->user()->threads()->create($request->all());

      $thread->tags()->attach($request->tags);

        //redirect
      return back()->withMessage('Thread Created!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Thread $thread
     * @return \Illuminate\Http\Response
     */
    public function show(Thread $thread)
    {
      foreach ($thread->comments as $comment) {
        $categories[] = $this->classify($comment->body);
        $nfakta[] = $this->nfakta;
        $nhoax[] = $this->nhoax;
      }

      $coba = $this->test;

      

      return view('thread.single', compact('thread', 'categories', 'coba', 'nfakta', 'nhoax'));
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

 public function train($sentence, $category) {
  $hoax = Category::$HOAX;
  $fakta = Category::$FAKTA;

  if ($category == $hoax || $category == $fakta) {


                // memasukan sentence ke table trainingSet dengan kateori sesuai dengan training data
          // $sql = mysqli_query($conn, "INSERT into trainingSet (document, category) values('$sentence', '$category')");
    $sql = DB::table('trainingSet')->insert(
      ['document' => $sentence, 'category' => $category]
    );

                // mengekstrak per kata ditandai dengan spasi
    $keywordsArray = $this -> tokenize($sentence);

                // meng update table wordFrequency
    foreach ($keywordsArray as $word) {


                    // jika kalimat ini sudah diberikan kategori maka akan mengupdate kalo tidak ada maka insert
       // $sql = mysqli_query($conn, "SELECT count(*) as total FROM wordFrequency WHERE word = '$word' and category= '$category' ");
      $sql = DB::table('wordFrequency')
      ->select(DB::raw('count(*) as total'))
      ->where([
        ['word', '=', $word],
        ['category', '=', $category],
      ])
      ->first();
      $count = $sql->total;

      if ($count['total'] == 0) {
            // $sql = mysqli_query($conn, "INSERT into wordFrequency (word, category, count) values('$word', '$category', 1)");
        $sql = DB::table('wordFrequency')->insert(
          ['word' => $word, 'category' => $category, 'count' => 1]
        );
      } else {
        // $sql = mysqli_query($conn, "UPDATE wordFrequency set count = count + 1 where word = '$word'");
        $sql = DB::table('wordFrequency')
        ->where('word', $word)
        ->update(['count' => count + 1]);
      }
    }

  } 
  else 
  {
   throw new Exception('Unknown category. Valid categories are: $fakta, $hoax');
 }
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


            $bodyTextIsHoax = ($pHoax); //log
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
                $this->nfakta = 0;
                $this->nhoax = 0;
                return $netral;
              }


// $this->test = $faktaCount ." dan ". $distinctWords." dan ".$wordCount;
              $bodyTextIsFakta += (($wordCount + 1) / ($faktaCount + $distinctWords)); //log

            }

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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Thread $thread
     * @return \Illuminate\Http\Response
     */
    public function edit(Thread $thread)
    {
      return view('thread.edit', compact('thread'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Thread $thread
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Thread $thread)
    {
//        if(auth()->user()->id !== $thread->user_id){
//            abort(401,"unauthorized");
//        }
//
      $this->authorize('update', $thread);
        //validate
      $this->validate($request, [
        'subject' => 'required|min:10',
        'type'    => 'required',
        'thread'  => 'required|min:20'
      ]);


      $thread->update($request->all());

      return redirect()->route('thread.show', $thread->id)->withMessage('Thread Updated!');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Thread $thread
     * @return \Illuminate\Http\Response
     */
    public function destroy(Thread $thread)
    {
//        if(auth()->user()->id !== $thread->user_id){
//            abort(401,"unauthorized");
//        }
      $this->authorize('update', $thread);

      $thread->delete();

      return redirect()->route('thread.index')->withMessage("Thread Deleted!");
    }

    public function markAsSolution()
    {
      $solutionId = Input::get('solutionId');
      $threadId = Input::get('threadId');

      $thread = Thread::find($threadId);
      $thread->solution = $solutionId;
      if ($thread->save()) {
        if (request()->ajax()) {
          return response()->json(['status' => 'success', 'message' => 'marked as solution']);
        }
        return back()->withMessage('Marked as solution');
      }


    }
    public function search()
    {
      $query=request('query');

      $threads = Thread::search($query)->with('tags')->get();

      return view('thread.index', compact('threads'));


    }
  }
