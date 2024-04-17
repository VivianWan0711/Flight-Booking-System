<?php
session_start();
//keep users selections and inputs as session variables
if (isset($_REQUEST['destination']) )
  $_SESSION['destination'] = $_REQUEST['destination'];
if (isset($_REQUEST['airline']))
  $_SESSION['airline'] = $_REQUEST['airline'];
if (isset($_REQUEST['email']))
  $_SESSION['email'] = $_REQUEST['email']; 
?>

<!DOCTYPE html>
<html lang='en-GB'>
  <head>
    <title>Flight booking system</title>
  </head>
  <body>
<?php
// The codes below was used to connect to the database, which were taken from the COMP284 Practical 7
$db_hostname = "studdb.csc.liv.ac.uk";
$db_database = "sgywan5";
$db_username = "sgywan5";
$db_password = "Wyx13767126546==";
$db_charset = "utf8mb4";


$dsn = "mysql:host=$db_hostname;dbname=$db_database;charset=$db_charset";
$opt = array(
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
); 

try {  
//create a pdo object to connect to the database
$pdo = new PDO($dsn,$db_username,$db_password,$opt);

  function availableFlights($pdo){
    // Use prepare statement to prevent SQL injection
    $showAll = "SELECT * FROM flights WHERE capacity>0 ORDER BY destination";
    $flightList = $pdo -> prepare($showAll);
    $flightList -> execute();
    
    // build a table to display all the flight information on the condition that result set is not empty
    if ($flightList->rowcount() > 0) {
      echo "<h1>Available flights: </h1><br>\n";
      echo "<table border=1 width=500 height=400>
            <tr><th>destination</th>
                <th>airline</th>
                <th>capacity</th>
                <th>base_price</th></tr>";      
      while ($flight = $flightList->fetch()) {
        echo "<tr><td>",$flight[destination], "</td>";
        echo "<td>",$flight[airline], "</td>";
        echo "<td>",$flight[capacity],"</td>";
        echo "<td>",$flight[base_price],"</td></tr>";
      }
      echo "</table><br><br>";      
    } else {
      echo "Sorry, all the flights have been booked.\n";
    }
  }

  // First stage: selection of airline information
  function selectDestination($pdo) {
    availableFlights($pdo);
    
    //retrieve distinct destinations which have seats from database alphabetically
    $destinationSql = "SELECT DISTINCT destination FROM flights WHERE capacity>0 ORDER BY destination";
    $destinationList = $pdo -> prepare($destinationSql);
    $destinationList -> execute();
    
    //put these destinations in a dropdown menu
    echo "
    <form name='form1' method='post'>
      <select name='destination' onChange='document.form1.submit()'>
          <option value=''>Please select a destination</option>";
        while ($row = $destinationList->fetch()) {
          echo "<option value=$row[destination]>$row[destination]</option>";
        }
    echo "
      </select>
    </form>"; 
  }

  //Second stage: selection of airline information
  function selectAirline($pdo) {
    availableFlights($pdo);             
    
    //retrieve airlines which have seats from database alphabetically
    echo "Your destination: ".$_SESSION['destination'];
    $airlineSql = "SELECT airline FROM flights WHERE destination ='".$_SESSION['destination']."' and capacity>0 ORDER BY airline";
    $airlineList = $pdo -> prepare($airlineSql);
    $airlineList -> execute();
    
    //put these airlines in a dropdown menu
    echo "
    <form name='form2' method='post'>
      <select name='airline' onChange='document.form2.submit()'>
          <option value=''>Please select an airline</option>";
          while ($row = $airlineList->fetch()) {
            echo "<option value=$row[airline]>$row[airline]</option>";
          }                 
    echo "
      </select>
    </form>"; 
  }
  
  //Third stage: calculating price based on capacity and baseprice
  function calculatePrice($pdo) {
    echo 'destination: ', $_SESSION['destination'], '<br>';
    echo 'airline: ', $_SESSION['airline'], '<br>';
  
    $priceSql = "SELECT base_price, capacity FROM flights WHERE destination ='".$_SESSION['destination']."' AND airline ='".$_SESSION['airline']."'";
    $priceList = $pdo -> prepare($priceSql);                  
    $priceList -> execute();                    

    while ($row = $priceList->fetch()) {
      echo "baseprice:".$row[base_price]."<br>capacity:".$row[capacity]."<br>";             
      $base_price=$row[base_price];
      $capacity=$row[capacity];
      $price=$base_price-(100*$capacity);
      $_SESSION['price']=$price;
      echo "price:".$_SESSION['price'];
    }
    //prompts user to enter email address
    formEmail();
  }
    
  function formEmail(){
  //if user clicks submit button, the email will be submitted and checked 
      echo'<form name="email" action="flights.php" method="post">
            <label>email: <input type="text" name="email"></label></form>';                     
      echo "<input type='submit' onClick='document.email.submit()'>";      
  }  
  
  //if the email is valid, continue to process the booking request; otherwise the request failed
  function checkEmail(){                             
    echo $_SESSION['email'];
    //a string which has exactly one occurrence of @ preceded and followed by a non-empty sequence of character a-z,A-Z,0-9,dot,hyphen,underscore will      considered as a valid email address
    if(preg_match('/\A[a-zA-Z0-9\.\-\_]+@[a-zA-Z0-9\.\-\_]+\Z/',$_SESSION['email'])){       
      processRequest($pdo);       
    }
    else{
      echo "Invalid email, booking failed!";
      //clean the session
      session_unset();
      session_destroy(); 
      $pdo = NULL;
    }  
  }
  
  
  function processRequest($pdo){
    echo "<h2>Your booking information: </h2><br>\n";
    echo'Destination: ',$_SESSION['destination'],'<br>';
    echo'Airline: ',$_SESSION['airline'],'<br>';
    echo'Price: ',$_SESSION['price'],'<br>';                    
    echo'Email: ',$_SESSION['email'],'<br><br>';
    
  //Below codes deal with the situation where two users nearly simultaneously try to book the last remaining empty seat on a flight. Retrieve the selected flight from database again to check whether there is empty seat in case the last empty seat has been booked by another user while this user was entering email address 
    $flightSql = "SELECT * FROM flights WHERE destination ='".$_SESSION['destination']."' AND airline ='".$_SESSION['airline']."'";
    $flightList = $pdo -> prepare($flightSql);                   
    $flightList -> execute();
    
    while ($flight = $flightList->fetch()) {
      if($flight[capacity]>0){
        // Store booking information in the form "bookings"
        $insertSql = "INSERT INTO bookings(destination,airline,price,email) VALUES(?,?,?,?)";
        $insert = $pdo -> prepare($insertSql);
        $insertSuccess = $insert -> execute(array($_SESSION['destination'],$_SESSION['airline'],$_SESSION['price'],$_SESSION['email']));
        
        // Update the capacity of the flight in table "flights"
        $updateSql = "UPDATE flights SET capacity=capacity-1 WHERE destination=? and airline=?";
        $update = $pdo -> prepare($updateSql);
        $updateSuccess = $update -> execute(array($_SESSION['destination'],$_SESSION['airline']));
        
        echo 'Book successfully!';
      }
      else{echo "No seats, booking failed!";}    
    }    
    // Clean the session
    session_unset();
    session_destroy();
  }
  
  if(isset($_SESSION['email']) && !empty($_SESSION['email']) && checkEmail()) {
    //Executing fourth stage
    checkEmail();
  } elseif(isset($_SESSION['airline']) && !empty($_SESSION['airline'])) {
    //Executing third stage    
    calculatePrice($pdo);
  } elseif (isset($_SESSION['destination']) && !empty($_SESSION['destination'])) {
    // Executing second stage
    selectAirline($pdo);
  } else {
    // Executing first stage
    selectDestination($pdo);
}

} catch (PDOException $e) {
  exit("PDO Error: ".$e->getMessage()."<br>");
}
?>
  </body>
</html>
