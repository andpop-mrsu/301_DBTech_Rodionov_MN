#!/bin/bash
py make_db_init.py
sqlite3 movies_rating.db < db_init.sql
echo DB created!
pause