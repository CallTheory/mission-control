# Database Health

A utility that provides insight into your SQL server and database health.

## Database Server

Details on your database server, edition, and more.

```
Hostname 	Edition 	                Clustered 	        Multi-User
sql1 	        Standard Edition (64-bit) 	Non Clustered instance 	Multi user
```

> Microsoft SQL Server 2019 (RTM-CU18-GDR) (KB5021124) - 15.0.4280.7 (X64) Jan 23 2023 12:37:13 Copyright (C) 2019 Microsoft Corporation Standard Edition (64-bit) on Windows Server 2019 Datacenter 10.0 (Build 17763: ) (Hypervisor)


## Volume Details

Details on your disks and volumes.

```
Logical Name 	Drive 	Free Space 	Total Space 	Occupied Space
SQLVMDATA1 	F:\ 	385.01 GB 	1022.98 GB 	637.97 GB 
```

## Database Details

Details on the individual databases your user has access to.
```
ID 	Database 	Created 	Owner 	    User Access 	Compatability 	Recovery 	Size
10 	Intelligent     1 year ago 	amtelco     MULTI_USER          150 	        FULL 	        16 MB 
```


## Backup Status

Details on the backup status of your databases.

```
Intelligent 	Log 	Last backup finished on 2023-05-26 22:48:56
Intelligent 	Full 	Last backup finished on 2024-06-12 21:08:35 
```

## Intelligent Database

Details on the individual tables within your Amtelco Intelligent Database server. 

Easily see the tables that take up the most room in your database.
```
Table 	        Rows 	    Reserved (MB) 	Data (MB) 	Index (MB) 	Unused (MB)
vlogStreams 	188,854 	460110.75 98% 	460038.82 	460025.18 	71.93
statEmail 	758,120 	2378.48 1% 	    2376.92 	2210.81 	1.56 
```
