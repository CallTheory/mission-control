# Datasources

You will need to configure the datasources of your system before attempting to use system features or utilities.

## Intelligent Series Database Connection

- **Database Host Server**: `sql-clustr.internal.yourdomain.com` or the network address of your SQL server
- **Database Port**: `1433` or the SQL port of your SQL server
- **Default Database**: `intelligent` or the name of your Amtelco IS database
- **Database Username**: `intelligent` or the username of your Amtelco IS database
- **Database Password**: `password` or the password of your Amtelco IS database

You will need to confirm the password every time you make changes to the database connection.

> All values are stored encrypted in the database. 


## Intelligent Series Web REST API Endpoint

- **Endpoint URL**: Enter the https ISWeb endpoint for your mobileIS.svc i.e., https://yourdomain.com/isweb/mobileIS.svc

> Used for MergeComm integration and other features that require the ISWeb API.


## Intelligent Series Service Account

The service account is used when connecting to the ISWeb API. We recommend you create a dedicated account for Mission Control.

- **Intelligent Series Agent Username**
- **Intelligent Series Agent Password**

You will need to confirm the password every time you make changes to the database connection.


## Intelligent Series Inbound SMTP

Enter the hostname for your intelligent server i.e.,`is.internal.yourdomain.com` and the associated port, typically 25. 

- **IS SMTP Email Host**
- **IS SMTP Email Port**

> You can find the information in Intelligent Series Supervisor → System → Email → Inbound SMTP settings.


## Client Database

An optional secondary SQL Server connection for client-specific data.

- **Database Host Server**: The network address of your client database SQL server
- **Database Port**: `1433` or the SQL port of your client database server
- **Default Database**: The name of your client database
- **Database Username**: The username for your client database
- **Database Password**: The password for your client database

> All values are stored encrypted in the database.


## CTE

Configure the CTE (Call Theory Engine) connection for advanced call processing features.


## IS User Directory

Credentials used for Intelligent Series user directory operations. We recommend creating a dedicated service account for Mission Control.

- **Intelligent Series Agent Username**
- **Intelligent Series Agent Password**

> Used for directory lookups, contact searches, and other user-level IS operations.


## MDR

Configure the MDR (Message Detail Record) data source connection.


## MiTeamWeb

Configure the MiTeamWeb URL for integration with the MiTeam collaboration platform.

- **MiTeamWeb Site URL**: The full URL of your MiTeamWeb instance (e.g., `https://miteamweb.yourdomain.com`)


## Marketing Site

Configure the URL of your organization's marketing website for use with Mission Control features.

- **Marketing Site URL**: The full URL of your marketing website (e.g., `https://www.yourdomain.com`)
