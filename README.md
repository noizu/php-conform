PHPUnit Friendly BDD Tests. 
====
  These bits are still fairly rough but should function as long as you have phpunit version 3.7.x
  
  to test execute 
```
phpunit --testdox --bootstrap bootstrap.php . 
```

from the test folder. 

Output should look like the following: 



```
**************************************************
** Story: Step Functions (Passed)               **
**************************************************

  Steps:
    [+] Given a php conform test
    [+] When running phpunit
    [+] Then step functions from the test suite class should be callable
    [+] And step functions included by the class should be callable
    [+] And step functions included for only this test should be callable

  Scenario Details:
    Total Asserts:0


**************************************************
** Story: Regular Expressions (Exception)       **
**************************************************

  Steps:
    [+] Given a php conform test
    [+] When we call a step function that uses a regular expression syntax to match and store the value of 5
    [+] Then the step function should have recieved the specified param value of 5

  Scenario Details:
    Total Asserts:1


**************************************************
** Story: Dollar Sign Notation (Exception)      **
**************************************************

  Steps:
    [+] Given a php conform test
    [+] When we call a step function that uses the dollar sign notation to match and store the value of 7
    [+] Then the step function should have recieved the specified param value of 7

  Scenario Details:
    Total Asserts:1


********************************************************
** Story: Example of a Data Driven BDD Test (Multi)   **
********************************************************

  Steps:
    [M] Given a calculator
    [M] When I add <input_1> plus <input_2>
    [M] Then the total should be <output>

  DataSets:
     |input_1|input_2|output|
    0|  20   |  30   |  50  |
    1|   2   |   6   |   8  |
    2|   3   |   4   |   2  |

  Failures:
    DataSet(2): Failed - Failed asserting that 7 matches expected 2.

  Scenario Details:
    Total Asserts:6


***************************************************************************
** Story: Example of Data Driven BDD Test with a missing step. (Multi)   **
***************************************************************************

  Steps:
    [M] Given a calculator
    [M] When I add <input_1b> plus <input_2b>
    [M] When I use a a sentance with no matching step function
    [M] Then the Data Driven Test Should correctly mark the unimplemented step as pending for that data entry.
    [M] And the total should be <output>

  DataSets:
     |input_1b|input_2b|output|
    0|   20   |   30   |  50  |
    1|    2   |    6   |   8  |
    2|    3   |    4   |   2  |

  Failures:
    DataSet(0): Incomplete - Step Not Found: When I use a a sentance with no matching step function
    DataSet(1): Incomplete - Step Not Found: When I use a a sentance with no matching step function
    DataSet(2): Incomplete - Step Not Found: When I use a a sentance with no matching step function

  Scenario Details:
    Total Asserts:0


********************************************************
** Story: Example of A Failing Bdd Test (Exception)   **
********************************************************

  Steps:
    [+] Given a calculator
    [+] When I add 3 plus 3
    [-] Then the total should be 8
      Failed: Failed asserting that 6 matches expected 8.

  Scenario Details:
    Total Asserts:2


************************************************************
** Story: Example of An Incomplete BDD Test (Exception)   **
************************************************************

  Steps:
    [+] Given a calculator
    [+] When I multiply 3 by 5
    [+] Then the total should be 15
    [P] And the calculator should sing a little tune

  Scenario Details:
    Total Asserts:2


*****************************************************
** Story: Setup and Teardown Methods (Exception)   **
*****************************************************

  Steps:
    [+] Given a php conform test
    [+] When running phpunit
    [+] Then the setup method should be called
    [+] And the teardown method should be called

  Scenario Details:
    Total Asserts:2


OldStyleSpec
 [x] P h p conform plays nicely with legacy p h p unit b d d scenarios

Standard
 [x] Standard strawberry
 [x] Standard apple
 [x] Standard bananna
```
