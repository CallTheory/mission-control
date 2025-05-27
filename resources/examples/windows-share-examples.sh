    id forge
    #returns uid=1001,gid=1001 which is used in the next command
    sudo mount -t cifs //server.domain.local/mfax /home/forge/mission-control/storage/app/mfax -o username=domainuser,password=redacted,domain=domain.local,uid=1001,gid=1001
    sudo mount -t cifs //server.domain.local/people-praise /home/forge/mission-control/storage/app/people-praise -o username=domainuser,password=redacted,domain=domain.local,uid=1001,gid=1001



