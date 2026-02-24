<?php
//global variable to connect database
$mySQL_Connection = null;

/***********************************************************************
 * Function: mySQLConnection :  To estaqblish a connection to database
 * Inputs   :   No
 * Outputs  :   No 
 *************************************************************************/

 function mySQLConnection()
 {
    //recalling the global variable
    // must recall global variable to use in functions
    global $mySQL_Connection;

    /*
     To establish connection
        1.URL
        2.UserName
        3.Password
        4.Databse
    */

    //these credentials are dummy credentintials (not Valid credentials)
    $mySQL_Connection = new mysqli("localhost", "UserName", "Password", "sandipt1241_phpDatabase");

    if ($mySQL_Connection->connect_errno)
    {
        error_log("Connection Error!!!");
        die();
    }

    error_log("Connection is established..");
}

/*************************************************************
Function : mySQLConnection : To execute retrival queries
Inputs   : Query  
Outputs  : Set Result or false
**************************************************************/

function mySelectQuery($myquery)
{
    //global variable recall
    global $mySQL_Connection;

    //querry results assigned as result
    $result = $mySQL_Connection -> query($myquery);

    //when the entered query is wrong
    if (!$result)
    {
        error_log("Error in query execution........");
        return false;
    }

    //if the querry is executed
    else
    {
        error_log("Entered the DbUtil file");
        return $result;
    }
}

/*************************************************************
Function : mySQLCloseConnection : Close DB connection
Inputs   : None
Outputs  : None
**************************************************************/
function mySQLCloseConnection()
{
    global $mySQL_Connection;
    if ($mySQL_Connection instanceof mysqli)
    {
        $mySQL_Connection->close();
    }
    $mySQL_Connection = null;
}

?>