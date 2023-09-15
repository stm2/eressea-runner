 * TODO missing passwd file after 1st turn
 * DONE execute.lock unnecessary because of LOCKFILE?
 * DONE orders-*: lock_file die or unlink?
 * DONE create-oders: check exit status and die or press on?
 * DONE remove lock
 * DONE install etc/procmail,fetchmail...
 * DONE install server/Mail
 * NOT install no runturn.lua
 * (DONE) procmail in requirements
 * DONE eressea.ini checker options , checker template
 * DONE eressea.ini email, sender, name
 * DONE turn 1 in tutorial?


  * these are all (?) the scripts:
* scripts deprecated by this script are marked with \*

* orders-accept: called by procmail; parse email, write orders.queue and orders.dir; works with orders.queue.lock
 * eorders.py: used by orders-process/accept; helper
 * epasswd.py: used by orders-accept, checkpasswd; checks passwords against file

* sendreport.sh: called by procmail; sends report to other address, depends on 1234.sh in reports
 * functions.sh: used by sendreports, sendreport; helper
 * checkpasswd: called by sendreport.sh, calls EPasswd


* orders.cron: called by cron; calls orders-process
 * orders-process: called by orders.cron; check passwd, echeck, send confirmation; create lockfile for orders.queue; backs up and removes orders.queue after timeout, removes orders.queue
  * checker.sh: called by orders-process; configured in eresse.ini: game.checker
  * eorders.py

* *run-eressea.cron: called by cron (or hand); waits for queue.lock, calls create-oders, backup, run-turn, sends reports
 * create-orders: called by run-eressea.cron; creates orders.\$turn from orders.dir, moves orders.dir to orders.dir.\$turn; works with orders.queue.lock
 * (\*)backup-eressea: called by run-eressea.cron
 * (\*) run-turn: called by run-eressea.cron; runs server once
 * (\*) compress.sh: called by run-eressea.cron; calls compress.py
  * compress.py: called by compress.sh; compresses reports of factions in reports.txt, creates 1234.sh scripts for reportnachforderung
 * *sendreports.sh: called by run-eressea.cron; send all reports, uses 1234.sh in reports
  * functions.sh
   * send-bz2-report: called by 1234.sh in reports; email one faction's report
   * send-zip-report: more modern version

* run-turn.sh: called by cron or hand; calls update, send (one or all, via mutt), fetchmail (and thus procmail), create_orders, write_reports, run_turn, or run_all;

* preview.cron: runs preview reports

* tests/run-turn.sh: called by integration build
