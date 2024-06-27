# Driver: Microsoft SQL Server

The SQLServer can be connected using both FreeDTS / Dblib (Sybase) or SQLSVR driver. 

They have specifics, but both are able to connect to SQLServer. 

There are some specifics as you can see below.

## The  Date format Issues

Date has the format `"Jul 27 2016 22:00:00.860"`. The solution is:

Follow the solution:
[https://stackoverflow.com/questions/38615458/freetds-dateformat-issues](https://stackoverflow.com/questions/38615458/freetds-dateformat-issues)
