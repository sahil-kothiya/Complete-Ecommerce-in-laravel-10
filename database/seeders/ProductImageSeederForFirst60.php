<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductImageSeederForFirst60 extends Seeder
{
    protected $images = [
        "00497a4a-3fc5-47c9-93ba-842393d35f46.webp",
        "00846c49-7137-4af6-8317-f1a7d578d8c2.webp",
        "0156bece-7587-4bb4-bf9c-00d0168d6656.webp",
        "021a2a91-7595-4999-9fd7-22465f160c43.webp",
        "0237a1ac-b56e-4c15-9789-8ddc9bd7f064.webp",
        "04cd210b-b297-48df-8813-bb4b1ff5c6c8.webp",
        "05331b5f-664e-42ee-9e50-877bf1377ecc.webp",
        "059926d6-36fc-4a13-8f0c-b3b45e1233de.webp",
        "0707c8a3-4490-47cd-9cda-fcfde0f06cef.webp",
        "07a0fd0b-a61f-4b2f-bc7a-94de73e74837.webp",
        "08e639f0-fbd4-4fdd-858d-97028cacb2e6.webp",
        "095e4164-fc6a-496a-82be-1a4329dde539.webp",
        "1409e8df-fafa-4aee-b2f6-7219ff79f065.webp",
        "1417dd87-781e-4a1e-95db-4e8c1211c79d.webp",
        "14cb9bfb-418c-4d22-857b-65d9da9cb0df.webp",
        "1508966f-1038-476f-919d-4b5dd1666d90.webp",
        "15408c36-b7df-4f2a-815a-fc3bc09358b6.webp",
        "17151bee-8464-4457-9f79-9a060665e86e.webp",
        "180de963-d17b-4a22-8857-401e90f1b963.webp",
        "181b0b1e-8e21-4327-91d5-52252d40e39d.webp",
        "1956ec81-3ad2-4faa-be22-b88b0d63f50d.webp",
        "206ff910-bbf7-4ed1-9a70-f300205815b5.webp",
        "20ba8fb4-f3b6-4f27-8b9a-341b4053c32a.webp",
        "20d1ef96-0cb8-421d-a556-dfda6d72f4d9.webp",
        "21ed957a-df01-42b7-b2ce-3441a20af592.webp",
        "22af1205-ead5-474b-91b6-de89553b039a.webp",
        "2339fe8a-4e2b-4ff9-9cdf-dca5c2279ea6.webp",
        "2501e267-fbbe-4ade-9b77-7d4304ba6b27.webp",
        "254e6ade-127b-4224-bf9e-a7419b87b57d.webp",
        "2563e59a-d467-412c-ab3c-9294d559fc70.webp",
        "2627a0e5-6cd9-487b-b75b-0025658adcbe.webp",
        "276010a7-5506-4481-875e-3cd04a8ee25c.webp",
        "3199ab14-3c02-4b80-bb07-1d676d1b7da0.webp",
        "3335010e-f606-4a2b-83b5-49ff03ee46ad.webp",
        "33d651c7-eb24-4c88-9bf6-970c701a225f.webp",
        "346d999f-2947-4bb0-835f-92b05fdd8317.webp",
        "34fd23d1-9447-4b08-b237-e84b208221ec.webp",
        "37a54979-07ee-4cca-a773-038c97030919.webp",
        "392dc0a3-9e6c-4095-a577-1be927dbc491.webp",
        "3c7881a0-db22-4c68-a18c-c119fe33157f.webp",
        "3c812305-91bf-40d5-8301-b5c4827bb68f.webp",
        "3fb6a907-39a3-4df8-af85-255c10bf2637.webp",
        "42ceb321-8679-4bed-adcf-2b48b050f4ad.webp",
        "43763d10-71a3-4f84-b298-8947ffb17bbf.webp",
        "4480e6e9-51dd-4f11-a094-06c2d0f69108.webp",
        "449dd908-0025-4450-b14d-f8de30a38e4e.webp",
        "44dfed28-ef5e-4b15-bf9f-889effd593f9.webp",
        "490ba931-e182-4f4a-9187-1eda9c738bdf.webp",
        "4b2a18d4-f26e-4507-9257-4719d5b94f9f.webp",
        "4b7773db-2610-435e-89ea-ace6e2554881.webp",
        "4eb38d66-aa49-42ef-b66e-d703c0c2b554.webp",
        "506280b8-ffb7-4a54-b42c-8b82c40cbd65.webp",
        "5190f28a-35e0-461b-aa75-55a0ad911dbf.webp",
        "52a5c396-7d47-425a-bdc5-d76d1c82fad4.webp",
        "53cc2ba3-561d-46f7-8fcf-3b8525c59b76.webp",
        "54aea5d4-b8d9-4bd1-9516-5bd2c4922feb.webp",
        "54b03731-8d2b-4519-8b20-bcd1f2a5dc06.webp",
        "54b931f5-692a-4df5-9337-929ee022b313.webp",
        "54f09718-56a6-49f5-8bee-dda0c230ef85.webp",
        "56e68d4f-2c12-42da-b145-9e3f08199814.webp",
        "576b3068-3192-4f77-8f32-a772599b91c7.webp",
        "577d68e0-d03b-4558-b362-11edf71e08e6.webp",
        "5833f3e4-f43f-477c-a817-31825a20cc8b.webp",
        "59a4f8b5-afac-41ba-85ff-d551a6c80573.webp",
        "59bce5f3-b433-4af5-bd18-9703fd277536.webp",
        "5b9d2198-ddbe-424e-98ac-83eda7f537ea.webp",
        "5bfc4a24-0603-45ac-ae76-6cb4d9547750.webp",
        "5c57fdd5-7a65-422d-829f-10decbe81e34.webp",
        "5d42d176-1b52-43b8-9656-5f8df44910ce.webp",
        "5e5158e8-c2ca-4d15-a1fe-fb161a718ad8.webp",
        "5f40c65b-f3fc-4187-a43a-f3a518bfd0e5.webp",
        "5fa42928-ade8-4982-b3e4-4f7e37639b41.webp",
        "5fab7109-9d6a-47ee-9b9f-646c9779d42a.webp",
        "6097495c-eb0a-42c2-a7d0-6ea9c8fdbe6f.webp",
        "61d48963-ee82-4817-aef1-cee40b04797a.webp",
        "61e2898e-617b-493a-a6d5-286529618c58.webp",
        "6294d963-fbe8-4c6b-81cc-0d0247b166bf.webp",
        "62ca90cc-77a8-4e6f-8d04-01bb0118eab1.webp",
        "6466e73f-fe9b-47f2-baa1-99c7e0812d58.webp",
        "656ee23d-bcd2-4dc1-a18f-14f3e4285267.webp",
        "6626de1e-0bad-445e-9c74-084e43c69b95.webp",
        "69da07c2-42f7-479c-b01f-536a8082cbe4.webp",
        "6a3a8272-43fd-4bfc-83ca-f5eddf910d97.webp",
        "6b1da216-7072-4918-9077-c2f5aa0b75ca.webp",
        "6b866d5c-1049-4d7c-b563-9de8e37ecd2f.webp",
        "6bd798b8-bde7-4809-a624-963eda7ffe30.webp",
        "6d15cc45-63b0-4458-9d3c-fbad0f1459c0.webp",
        "6d28eef2-998f-4de8-bb74-87bba6dd0146.webp",
        "6e096ebf-0908-47f6-84f7-0d9f2566a501.webp",
        "6e2b528a-f307-4600-8eaf-b79b7f2512c8.webp",
        "6e53dc58-85e8-4f4f-a688-6c3889cb3bee.webp",
        "6f7c39b4-0803-40d0-b7b9-a3eb094afd37.webp",
        "7190d555-af5a-4049-8e82-a879f88127b3.webp",
        "7205ad2f-705d-43b2-a9ac-2a7a8b6237db.webp",
        "7360f2e9-bcfc-41e5-9d09-9d0d5d0a2b37.webp",
        "73cb08ba-f144-4c8c-826c-f101d9216586.webp",
        "744306f6-4a9d-4e63-b277-23c017085237.webp",
        "775f2205-848b-40c4-b48d-ab6dcd785554.webp",
        "776aab22-ec00-4a2c-bad4-ea01aba9017e.webp",
        "77dbc725-aed6-4e3f-a449-77bafe60b697.webp",
        "79207a1d-8f93-49ba-9948-847d9b0a9821.webp",
        "797bc909-60b3-4847-9398-67a86ac93ebb.webp",
        "7b0c951a-68dd-4554-a64d-35a710fb1fa6.webp",
        "7b49c5d5-c0a6-4ef5-8d42-9c464389ec3a.webp",
        "7c02bc19-6371-4d88-bb8f-bfb023c280dd.webp",
        "7c0506b7-755f-423b-9979-f110470cb4a4.webp",
        "7c26316a-20cd-4827-a6f4-435a62c246cd.webp",
        "7cf946ec-ae9c-415f-9923-b785a719ffa0.webp",
        "7f493e57-c09e-4c88-ac7a-9341b4c788ca.webp",
        "8122c116-6b27-497d-a6a2-9ff138405121.webp",
        "818097b1-03fa-49f8-b98e-73b932bbeb1b.webp",
        "82ff762c-2272-4997-9d6c-cb1e32662ddf.webp",
        "83730e92-bbea-4417-b1c7-e966d1566477.webp",
        "8388df12-e701-4a23-9f4c-a2b3b3c8780d.webp",
        "83bea5ee-2820-421d-80be-c9d5449f9ed3.webp",
        "85558907-feb6-4b54-870c-4f3970f3ceac.webp",
        "85cea55f-dc8c-4896-a1f0-5d05599e779c.webp",
        "88dddfeb-afdc-43f6-ae1b-9f9c71cc2bcf.webp",
        "892d83be-be16-4b16-90a6-fab508ee2ed9.webp",
        "89d08686-9cbf-4f2e-9f99-fc7cda2005e5.webp",
        "8cdea5b9-17fb-4a65-bbd1-078064d3b593.webp",
        "8fd5a6ab-c3c5-494d-9212-846b2f34ba5a.webp",
        "90ac8e43-ac74-47d9-b5f3-02258b07f90b.webp",
        "91a17b49-8ef2-4d25-95b2-dcae1487ffce.webp",
        "93c282a1-28d9-46ab-a5fe-7d6dbdeac83c.webp",
        "961de4a3-8263-45c3-9cad-d2cd08976e53.webp",
        "97637309-d1af-4376-beba-c7d6970637c7.webp",
        "97f79b5a-8365-424b-9718-cdd909075f92.webp",
        "9a0d992e-5c64-4ac2-80b8-76b3f9c56862.webp",
        "9c1b2d04-3d33-4884-9ace-25935ec220f4.webp",
        "9dd4f960-4f15-429f-a081-370c1b54e2c3.webp",
        "9ee186fc-1227-4acc-8197-7740a0886e3d.webp",
        "9ef7d602-dedb-4a01-85e6-68bfd1e5eacd.webp",
        "9f31e5d1-33e3-45ad-a403-608a0a40b6b8.webp",
        "9fd6b31f-6437-40f8-aa5d-30275289e08d.webp",
        "a0b447cb-a774-4e28-92ab-e32debdec19b.webp",
        "a15d9fea-1c86-4b98-aeae-13dc2d3a2025.webp",
        "a1823ab5-0a2b-44b5-aa93-9e402710240d.webp",
        "a259d402-cb17-476f-9109-f120621ed3c0.webp",
        "a29ca1fe-40c8-4e21-be74-e281108d8cc0.webp",
        "a2b411b3-356a-488f-a0f7-ada9a9a55bee.webp",
        "a336c637-5260-43b0-a91e-06b5172a5b87.webp",
        "a48e9cfa-fdf4-434e-bea3-966c12f1794b.webp",
        "a59f8ae2-a237-4973-94f9-4f8db862afac.webp",
        "a70896e0-5de2-46bb-9866-ea6a7322092e.webp",
        "a79eead7-5743-41cc-8d4c-a7443f630683.webp",
        "a8769d80-2a3e-4b53-bf34-b342b0f236ab.webp",
        "a8a8ba0b-d30c-46cc-8b1c-5ae8b05fee84.webp",
        "a9d1961b-796f-4456-94bf-f26bd6c36d22.webp",
        "abb49a25-3dce-47ad-b6ad-cd8e5743fa61.webp",
        "ac253524-e830-4d0f-a546-75f6bac6f4ec.webp",
        "ad2f2568-a254-42ec-95ac-6ee1cd0bfd81.webp",
        "ad735a6f-b735-4d51-bffd-27d15f369b91.webp",
        "afb0e35f-3668-42f9-bfa8-7ae9dbe53fdb.webp",
        "b1d850c3-31ab-4ca0-8439-419601ada299.webp",
        "b30f4a96-fad1-4f21-840c-6b38475fc74b.webp",
        "b3262b3a-b9ad-4c6f-b46e-c98c2d256e01.webp",
        "b327d5d2-fa75-41a8-bacd-0ad165b7b14c.webp",
        "b44b6426-c880-4036-9b65-7a778cdddb5f.webp",
        "b71c8398-ed9e-4aea-9d09-fd2f63129444.webp",
        "b73f3892-108e-4b9f-b76d-43d7c443bf63.webp",
        "b8099a8b-a23a-46f3-8861-90affe952cf7.webp",
        "b966be47-8f16-4c94-86fc-d95242a2ab1a.webp",
        "bb1d1619-3338-418f-824c-4b59a436213e.webp",
        "bb4652aa-fc4a-4d53-a3d3-03719244c171.webp",
        "bc43c-image1xxl.webp",
        "bca2e060-942a-4cec-afeb-260ea7c190c4.webp",
        "bd7a2-pms000t.webp",
        "d3fdb-image2xxl-4-.webp",
        "f305b29f-7afc-4ba8-b9a0-56dcf9b273e8.webp",
        "f675d915-6630-46a8-98f8-33006ed1575c.webp",
        "f7042e18-5bd6-45a4-8da0-8b60ccbbf587.webp",
        "f85033ca-32c5-46ac-a2dd-530d2bf3abe1.webp",
        "fa0855ea-1b4f-4de1-8af8-6f76a14023b3.webp",
        "fa687950-ee02-433f-8500-e15d26e59344.webp",
        "fc5876f1-ba63-497d-867e-db0d1e2f38d2.webp",
        "fcbe7abb-650c-4712-ae24-6c3340f3236b.webp",
        "fdc37297-08d4-4b2f-ad75-a561d358536f.webp",
        "fe7dee33-7015-4d9c-bdf6-e4bc7f60234b.webp",
        "ff3310a1-c17a-40e6-a454-afdb689629f4.webp",
    ];

    public function run(): void
    {
        $images = collect($this->images); // should contain exactly 180 unique items

        if ($images->count() < 180) {
            $this->command->error('❌ You must provide at least 180 unique images.');
            return;
        }

        // Get first 60 products ordered by ID ascending
        $products = DB::table('products')->latest('id')->limit(60)->get()->sortBy('id')->values();

        // Delete existing images for these products
        DB::table('product_images')
            ->whereIn('product_id', $products->pluck('id'))
            ->delete();

        // Split 180 images into 60 sets of 3
        $imageChunks = $images->chunk(3)->values(); // [ [img1, img2, img3], ..., [img178, img179, img180] ]

        $insertData = [];

        foreach ($products->values() as $index => $product) {
            // Convert chunk to array to avoid collection issues
            $imageSet = $imageChunks->get($index)?->values() ?? collect();

            if ($imageSet->count() !== 3) {
                $this->command->error("❌ Image set for product ID {$product->id} is invalid (found " . $imageSet->count() . " images).");
                continue;
            }

            foreach ($imageSet as $imgIndex => $imageName) {
                $insertData[] = [
                    'product_id' => $product->id,
                    'image_path' => 'storage/photos/1/Products/' . $imageName,
                    'is_primary' => $imgIndex === 0 ? 1 : 0,
                    'sort_order' => $imgIndex,
                ];
            }
        }


        // Insert all images
        DB::table('product_images')->insert($insertData);

        $this->command->info("✅ 180 images inserted for first 60 products (3 per product, 1 primary each).");
    }
}
