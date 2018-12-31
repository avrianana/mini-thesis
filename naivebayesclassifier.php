<?php


require_once('Category.php');


class NaiveBayesClassifier {

   public function __construct() {
   }

        /**
         * sentence adalah text(document) yang akan diklasifikasikan apakah fakta/hoax
         * @return category- ham/spam
         */
        public function classify($sentence) {

            //extract keyword dari dalam text/setence
          $keywordsArray = $this -> tokenize($sentence);

    		// mengklasifikasikan kategori
          $category = $this -> decide($keywordsArray);

          return $category;
      }

    	/**
    	 * @sentence- text/document yang disediakan user sebagai data training
    	 * @category- kategori dari sentence
         *fungsi ini akan menyimpan setence atau text/dokumen di dalam tabel trainingset dengan kategori yang diberikan
         *dan akan menambah atau mengupdate jumlah dari kalimat didalam table wordFrequency atau membuat baru
    	 */
    	public function train($sentence, $category) {
    		$hoax = Category::$HOAX;
    		$fakta = Category::$FAKTA;

    		if ($category == $hoax || $category == $fakta) {

	            //konek db
              require 'db_connect.php';

	    	    // memasukan sentence ke table trainingSet dengan kateori sesuai dengan training data
              $sql = mysqli_query($conn, "INSERT into trainingSet (document, category) values('$sentence', '$category')");

	    	    // mengekstrak per kata ditandai dengan spasi
              $keywordsArray = $this -> tokenize($sentence);

	    	    // meng update table wordFrequency
              foreach ($keywordsArray as $word) {


                    // jika kalimat ini sudah diberikan kategori maka akan mengupdate kalo tidak ada maka insert
                 $sql = mysqli_query($conn, "SELECT count(*) as total FROM wordFrequency WHERE word = '$word' and category= '$category' ");
                 $count = mysqli_fetch_assoc($sql);

                 if ($count['total'] == 0) {
                    $sql = mysqli_query($conn, "INSERT into wordFrequency (word, category, count) values('$word', '$category', 1)");
                } else {
                    $sql = mysqli_query($conn, "UPDATE wordFrequency set count = count + 1 where word = '$word'");
                }
            }

	    	    //tutup koneksi
            $conn -> close();

        } else {
         throw new Exception('Unknown category. Valid categories are: $fakta, $hoax');
     }
 }

    	/**
    	 * fungsi ini untuk mengambil paragraf dari text sebagai input lalu mengembalikannya ke array
    	 */

    	private function tokenize($sentence) {
            //758 kata dari: https://github.com/masdevid/ID-Stopwords/blob/master/id.stopwords.02.01.2016.txt
            require 'db_connect.php';

            $stopWords = array();

            $sql = mysqli_query($conn, "SELECT word_sw FROM stopwords");

            while($row = mysqli_fetch_array($sql))
            {
                array_push($stopWords,$row[0]);
                // $stopWords = $row;
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

    	/**
    	 * fungsi ini pake array dari semua kata sebagai input dan meng return nya ke category (fakta/hoax) setelah
    	 * dimasukkan ke Naive Bayes Classifier
    	 *
    	 * Naive Bayes Classifier Algorithm -
    	 *
    	 *   p(hoax/bodyText) = p(hoax) * p(bodyText/hoax) / p(bodyText);
    	 *   p(fakta/bodyText) = p(ham) * p(bodyText/ham) / p(bodyText);
    	 *   p(bodyText) is constant so it can be ommitted
    	 *   p(hoax) = total dari dokumen (sentence) punyanya si kategori hoax / total no dokumen (sentence)
    	 *   p(bodyText/hoax) = p(word1/hoax) * p(word2/hoax) * .... p(wordn/hoax)
    	 *   Laplace smoothing for such cases is usually given by (c+1)/(N+V), 
    	 *   where V is the vocabulary size (total no of different words)
    	 *   p(word/spam) = no of times word occur in spam / no of all words in spam
    	 *   Dari sini:
    	 *   http://stackoverflow.com/questions/9996327/using-a-naive-bayes-classifier-to-classify-tweets-some-problems
    	 *   https://github.com/ttezel/bayes/blob/master/lib/naive_bayes.js
    	*/
    	private function decide ($keywordsArray) {
    		$hoax = Category::$HOAX;
    		$fakta = Category::$FAKTA;

            // Defaultnya kita buat fakta aja biar gak sujon
    		$category = $fakta;

    		// konek ke db jgn lupa lagi
         require 'db_connect.php';

         $sql = mysqli_query($conn, "SELECT count(*) as total FROM trainingSet WHERE  category = '$hoax' ");
         $hoaxCount = mysqli_fetch_assoc($sql);
         $hoaxCount = $hoaxCount['total'];

         $sql = mysqli_query($conn, "SELECT count(*) as total FROM trainingSet WHERE  category = '$fakta' ");
         $faktaCount = mysqli_fetch_assoc($sql);
         $faktaCount = $faktaCount['total'];

         $sql = mysqli_query($conn, "SELECT count(*) as total FROM trainingSet ");
         $totalCount = mysqli_fetch_assoc($sql);
         $totalCount = $totalCount['total'];

    		//p(hoax) probabilitas dari hoax
    		$pHoax = $hoaxCount / $totalCount; // (jumlah dari dokumen yang diklasifikasikan sbg hoax / total documents)

    		//p(fakta) probabilitas dari fakta
    		$pFakta = $faktaCount / $totalCount; // (jumlah dari dokumen yang diklasifikan sbg fakta / total documents)

    		//echo $pHoax." ".$pFakta;

            // jumlah dari distinct kata
            $sql = mysqli_query($conn, "SELECT count(*) as total FROM wordFrequency ");
            $distinctWords = mysqli_fetch_assoc($sql);
            $distinctWords = $distinctWords['total'];

            $bodyTextIsHoax = log($pHoax);
            foreach ($keywordsArray as $word) {
               $sql = mysqli_query($conn, "SELECT count as total FROM wordFrequency where word = '$word' and category = '$hoax' ");
               $wordCount = mysqli_fetch_assoc($sql);
               $wordCount = $wordCount['total'];
               $bodyTextIsHoax += log(($wordCount + 1) / ($hoaxCount + $distinctWords));
           }

           $bodyTextIsFakta = log($pFakta);
           foreach ($keywordsArray as $word) {
               $sql = mysqli_query($conn, "SELECT count as total FROM wordFrequency where word = '$word' and category = '$fakta' ");
               $wordCount = mysqli_fetch_assoc($sql);
               $wordCount = $wordCount['total'];
               $bodyTextIsFakta += log(($wordCount + 1) / ($faktaCount + $distinctWords));
           }

           if ($bodyTextIsFakta >= $bodyTextIsHoax) {
               $category = $fakta;
           } 
           elseif ($bodyTextIsFakta == $bodyTextIsHoax) {
            $category = "Netral";
        }
        else {
           $category = $hoax;
       }

       $conn -> close();

       return $category;
   }
}

?>
