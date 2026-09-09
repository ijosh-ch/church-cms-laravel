<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The IFGF axis. Two rows today: Taipei (TPE) and Zhongli (ZL). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();          // TPE, ZL
            $table->string('name', 64);                   // Taipei
            $table->string('name_zh', 64)->nullable();    // 台北
            $table->string('timezone', 64)->default('Asia/Taipei');
            $table->string('gmaps_url', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_branches');
    }
};
