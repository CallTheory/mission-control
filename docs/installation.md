# Installation

Mission Control is a server appliance.

## Basic Requirements

- **Operating System**: Ubuntu 22.04 LTS or newer
- **CPU**: 1-2 Cores
- **Memory**: 4-8 GB
- **Storage**: 100 GB
- Available via HTTP/HTTPS on port 80/443 for automatic TLS certificate renewals
- Available SSH/22 TCP locked down to code-deployment and Call Theory IP addresses
- `curl` must be installed on the server (installed by default on Ubuntu)

> Storage is sized for caching rather than for the application itself. Converted screen
> captures, call recordings and transcription working files all land on local disk, so a
> busy call center should size up from the 100 GB baseline.

### What the installer sets up

You do not need to install any of this yourself — it is listed so you know what will be
running on the server.

| Component | Purpose |
|-----------|---------|
| PHP 8.4 | The application runtime |
| MySQL | Mission Control's own database (this is **not** your Intelligent Series database) |
| Redis | Cache, sessions, and the background job queue |
| Laravel Horizon | Queue worker supervision, viewable at `/queue` |
| Microsoft ODBC / `sqlsrv` | Connecting to your Intelligent Series SQL Server |
| `ffmpeg` | Screen capture conversion |
| `sox`, `lame`, `libsox-fmt-mp3` | Call recording and audio processing |
| Google Chrome (headless) | Screenshot generation for Board Check exports |

Mission Control never modifies your Intelligent Series database schema. It connects with
the credentials you provide under [Datasources](system/datasources.md).

## Installation Guide

- In your environment, create a virtual machine that meets your hardware requirements
- The virtual machine should be running the latest LTS from Ubuntu (currently 22.04 64bit)
- The virtual machine must have a `root` user
- The port security for TCP 22/80/443 must be configured before attempting to install Mission Control
- Take a snapshot of your virtual machine before we begin installation.

Once the server is up and running, Call Theory will provide you with an SSH public key to be placed in the `~/.ssh/authorized_keys` file for the `root` user.

We will then remote in via SSH and kick off the installation - which usually takes about 15-30 minutes. 
This automated process will install all necessary software and dependencies, configure the server, and start the Mission Control service. 
It will also apply security practices like disabling password authentication over SSH and enabling the firewall, among other steps.

> Call Theory will also install [SentinelOne](https://www.sentinelone.com) agent on the server unless you otherwise decline or install your own security solution. 

## Create Your Account

After installation is complete, you'll need to visit `yourserveraddress.tld/register` where you can create the initial administrative account. 

> This route is automatically disabled as soon as the first user is created.

## Logging In

After you have created your account, you can log in at `yourserveraddress.tld/login` to access Mission Control. You'll be greeted with your private (and empty) dashboard.

[Getting Started](getting-started.md)

